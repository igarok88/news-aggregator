import sys
import json
import asyncio
from googlenewsdecoder import decoderv1
from trafilatura import extract
from playwright.async_api import async_playwright

# Исправление кодировки для Windows консоли
sys.stdout.reconfigure(encoding='utf-8')

async def fetch_article(google_url):
    # По умолчанию считаем, что всё плохо
    result = {"status": "error", "url": google_url, "text": ""}
    
    target_url = google_url
    try:
        decoded = decoderv1(google_url)
        if decoded and decoded.startswith('http'):
            target_url = decoded
    except:
        pass

    async with async_playwright() as p:
        try:
            # Запускаем браузер с маскировкой под реального человека
            browser = await p.chromium.launch(headless=True, args=[
                "--disable-blink-features=AutomationControlled",
                "--no-sandbox",
                "--disable-setuid-sandbox"
            ])
            context = await browser.new_context(
                user_agent='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                locale='en-US',
                viewport={'width': 1920, 'height': 1080}
            )
            page = await context.new_page()
            
            try:
                await page.goto(target_url, wait_until="domcontentloaded", timeout=40000)
            except:
                pass

            # === УМНОЕ ЛЕЧЕНИЕ COOKIE WALL (Google / Yahoo / AOL) ===
            await page.wait_for_timeout(2000)
            
            consent_domains = ["consent", "guce", "cookie", "priva"]
            
            # Если мы попали на страницу согласия
            if any(x in page.url.lower() for x in consent_domains):
                # print(f"DEBUG: Пытаемся пробить стену: {page.url}", file=sys.stderr)
                
                # 1. Скроллим (триггер для Yahoo)
                try:
                    await page.mouse.wheel(0, 15000)
                    await page.wait_for_timeout(1000)
                except: pass

                # 2. Список селекторов (включая скрытые)
                # Yahoo часто прячет кнопку в <input type="submit">
                selectors = [
                    "button[name='agree']", 
                    "input[name='agree']",
                    "button[name='submit']",
                    "button.primary",
                    "input[value='Accept all']",
                    "input[value='Agree']",
                    "button:has-text('Accept all')",
                    "button:has-text('I agree')",
                    "button:has-text('Agree')",
                    "button:has-text('Yes')",
                    "form[action*='consent'] button" # Любая кнопка в форме согласия
                ]

                clicked = False
                
                # Проходим по всем фреймам и селекторам
                for frame in page.frames:
                    if clicked: break
                    for sel in selectors:
                        try:
                            loc = frame.locator(sel).first
                            if await loc.count() > 0:
                                # МАГИЯ ЗДЕСЬ: Кликаем через JavaScript (обходит перекрытия)
                                await loc.evaluate("node => node.click()")
                                clicked = True
                                await page.wait_for_timeout(5000) # Ждем редиректа
                                break
                        except:
                            continue

            # ==============================================================

            await page.wait_for_timeout(2000)
            final_url = page.url
            html_content = await page.content()

            # ФИНАЛЬНАЯ ПРОВЕРКА (Strict Mode)
            # Если мы все еще на странице согласия — это провал.
            # Нельзя отдавать текст соглашения как новость.
            if any(x in final_url.lower() for x in consent_domains):
                 result["status"] = "error"
                 result["message"] = f"Skipped: Blocked by Consent Wall ({final_url})"
                 result["text"] = "" # Возвращаем пустоту, чтобы не путать ИИ
            else:
                # Если URL нормальный, пробуем извлечь текст
                text = extract(html_content, include_comments=False, include_tables=False)
                
                # Проверка: текст должен быть длиннее 200 символов, иначе это мусор
                if text and len(text) > 200:
                    result = {
                        "status": "success",
                        "url": final_url,
                        "text": text
                    }
                else:
                    result["status"] = "error"
                    result["message"] = f"Текст слишком короткий или не найден. URL: {final_url}"
                
            await browser.close()
            
        except Exception as e:
            result["message"] = f"Playwright Error: {str(e)}"
            
    return result

if __name__ == "__main__":
    if len(sys.argv) > 1:
        url = sys.argv[1]
        try:
            data = asyncio.run(fetch_article(url))
            print(json.dumps(data, ensure_ascii=False))
        except Exception as e:
            print(json.dumps({"status": "critical_error", "message": str(e)}, ensure_ascii=False))
    else:
        print(json.dumps({"status": "error", "message": "No URL provided"}, ensure_ascii=False))