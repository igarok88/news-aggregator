# Import system library for command line arguments and I/O streams
import sys
# Import library for JSON formatting (data exchange with PHP)
import json
# Import library for asynchronous programming
import asyncio
# Import Google News link decoder (converts google.com/url... to real links)
from googlenewsdecoder import decoderv1
# Import Trafilatura library for extracting main text from HTML (removes ads, menus)
from trafilatura import extract
# Import asynchronous API for controlling the Playwright browser
from playwright.async_api import async_playwright

# Force UTF-8 encoding for standard output.
# Critical for Windows consoles to avoid crashing on emojis or non-ASCII characters.
sys.stdout.reconfigure(encoding='utf-8')

# Async function to download an article. Accepts a Google News URL.
async def fetch_article(google_url):
    # Initialize result with default error status.
    # If anything fails, we return this object.
    result = {"status": "error", "url": google_url, "text": ""}
    
    # Keep the original URL as the target initially
    target_url = google_url
    try:
        # Attempt to decode the Google News redirect URL
        decoded = decoderv1(google_url)
        # If decoding succeeded and looks like a valid URL
        if decoded and decoded.startswith('http'):
            # Update the target URL to the direct link
            target_url = decoded
    except:
        # If the decoder fails, ignore it and proceed with the original URL
        pass

    # Launch Playwright context manager (browser engine)
    async with async_playwright() as p:
        try:
            # Launch Chromium browser.
            # headless=True means the browser runs in the background (no GUI).
            browser = await p.chromium.launch(headless=True, args=[
                # Disable flags that reveal automation to websites
                "--disable-blink-features=AutomationControlled",
                # Disable sandbox (required for running inside Docker)
                "--no-sandbox",
                # Disable setuid sandbox (extra configuration for Linux/Docker)
                "--disable-setuid-sandbox"
            ])
            
            # Create a new browser context (similar to an incognito tab).
            # Set user agent and locale to mimic a real user on Windows 10.
            context = await browser.new_context(
                user_agent='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                locale='en-US', # Set locale
                viewport={'width': 1920, 'height': 1080} # Set Full HD resolution
            )
            
            # Open a new empty page in this context
            page = await context.new_page()
            
            try:
                # Navigate to the target URL.
                # wait_until="domcontentloaded" waits for the HTML tree (faster than full load with images).
                # timeout=40000 gives the site 40 seconds to load, otherwise it errors out.
                await page.goto(target_url, wait_until="domcontentloaded", timeout=40000)
            except:
                # If the page fails to load (timeout or network error), proceed anyway (content might exist)
                pass

            # === START BLOCK: SMART COOKIE WALL BYPASS (Google / Yahoo / AOL) ===
            
            # Wait 2 seconds for protection scripts to load and show popups
            await page.wait_for_timeout(2000)
            
            # List of keywords in the URL that indicate a consent/cookie page
            consent_domains = ["consent", "guce", "cookie", "priva"]
            
            # Check if current URL contains any of the stop-words
            if any(x in page.url.lower() for x in consent_domains):
                # (Commented out) Debug print if we hit a wall
                # print(f"DEBUG: Attempting to break wall: {page.url}", file=sys.stderr)
                
                # 1. Attempt scroll (heuristic for Yahoo, where the button appears after scrolling)
                try:
                    # Scroll down 15000 pixels
                    await page.mouse.wheel(0, 15000)
                    # Wait 1 second after scrolling
                    await page.wait_for_timeout(1000)
                except: pass # Ignore scroll errors

                # 2. List of CSS selectors to find "Accept", "Agree" buttons
                selectors = [
                    "button[name='agree']",       # Standard button with name='agree'
                    "input[name='agree']",        # Input field (common in older forms)
                    "button[name='submit']",      # Form submit button
                    "button.primary",             # Button with 'primary' class
                    "input[value='Accept all']",  # Input with value 'Accept all'
                    "input[value='Agree']",       # Input with value 'Agree'
                    "button:has-text('Accept all')", # Search button by text content (Playwright feature)
                    "button:has-text('I agree')",    # Variation 'I agree'
                    "button:has-text('Agree')",      # Variation 'Agree'
                    "button:has-text('Yes')",        # Variation 'Yes'
                    "form[action*='consent'] button" # Any button inside a consent form
                ]

                # Flag indicating if we successfully clicked a button
                clicked = False
                
                # Iterate through all frames (many consent forms are embedded via iframe)
                for frame in page.frames:
                    # If already clicked in another frame, break loop
                    if clicked: break
                    # Try every selector in the list
                    for sel in selectors:
                        try:
                            # Find the first element matching the selector in the frame
                            loc = frame.locator(sel).first
                            # If element exists (count > 0)
                            if await loc.count() > 0:
                                # MAGIC HERE: Click via pure JavaScript.
                                # This bypasses overlays that might block a standard click.
                                await loc.evaluate("node => node.click()")
                                # Set flag to true
                                clicked = True
                                # Wait 5 seconds for the site to process click and redirect
                                await page.wait_for_timeout(5000) 
                                # Break selector loop since button was found
                                break
                        except:
                            # If error occurs during search or click, try next selector
                            continue

            # ==============================================================

            # Final wait of 2 seconds for content to fully load after redirect
            await page.wait_for_timeout(2000)
            # Capture final URL (to check if we are still stuck on a cookie page)
            final_url = page.url
            # Get full HTML content of the page
            html_content = await page.content()

            # FINAL CHECK (Strict Mode)
            # If we are still on a consent/cookie page after all attempts -> Failure.
            if any(x in final_url.lower() for x in consent_domains):
                 # Update status to error
                 result["status"] = "error"
                 # Set message indicating blockage
                 result["message"] = f"Skipped: Blocked by Consent Wall ({final_url})"
                 # Return empty text to avoid feeding garbage to AI
                 result["text"] = "" 
            else:
                # If URL looks normal, use Trafilatura to extract text
                # include_comments=False removes user comments
                # include_tables=False removes tables (often contain financial data junk)
                text = extract(html_content, include_comments=False, include_tables=False)
                
                # Quality check: text must be longer than 200 chars.
                # If shorter, it's likely an error page, captcha, or empty template.
                if text and len(text) > 200:
                    # Construct success result
                    result = {
                        "status": "success",
                        "url": final_url,
                        "text": text # Cleaned article text
                    }
                else:
                    # If text is too short or missing
                    result["status"] = "error"
                    result["message"] = f"Text too short or not found. URL: {final_url}"
                
            # Close browser to free up resources
            await browser.close()
            
        except Exception as e:
            # Handle global Playwright errors (e.g., browser crash)
            result["message"] = f"Playwright Error: {str(e)}"
            
    # Return result dictionary
    return result

# Entry point when script is run directly
if __name__ == "__main__":
    # Check if URL argument is provided (python news_fetcher.py URL)
    if len(sys.argv) > 1:
        # Take the first argument as URL
        url = sys.argv[1]
        try:
            # Run async fetch function
            data = asyncio.run(fetch_article(url))
            # Print result as JSON (ensure_ascii=False preserves Unicode characters)
            print(json.dumps(data, ensure_ascii=False))
        except Exception as e:
            # Print critical execution error as JSON
            print(json.dumps({"status": "critical_error", "message": str(e)}, ensure_ascii=False))
    else:
        # If no URL argument provided
        print(json.dumps({"status": "error", "message": "No URL provided"}, ensure_ascii=False))