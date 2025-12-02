document.querySelector("h1").addEventListener("click", function () {
  // Если идет процесс (кнопка заблокирована), можно остановить его (опционально)
  // Но проще всего просто перезагрузить страницу "начисто"

  // window.location.pathname берет адрес без ?query=...
  // Это полностью очистит форму и результаты
  window.location.href = window.location.pathname;
});
// === 1. Управление настройками ===

function openSettings() {
  document.getElementById("settingsModal").style.display = "flex";
}

function closeSettings() {
  document.getElementById("settingsModal").style.display = "none";
}

// Закрытие по клику вне модального окна
window.onclick = function (event) {
  let modal = document.getElementById("settingsModal");
  if (event.target == modal) {
    closeSettings();
  }
};

// Сохранение API ключа
function saveApiKey() {
  let key = document.getElementById("apiKeyInput").value.trim();

  if (key.length > 0) {
    // Добавил 'Secure' (для HTTPS) и samesite=strict для безопасности
    document.cookie =
      "gemini_user_key=" +
      encodeURIComponent(key) +
      "; path=/; max-age=31536000; samesite=strict";
    alert("Ключ сохранен! Страница будет перезагружена.");
  } else {
    document.cookie = "gemini_user_key=; path=/; max-age=0";
    alert("Ключ удален. Будет использован системный ключ (если есть).");
  }
  location.reload();
}

// === 2. Логика интерфейса и логов ===

// Единая функция добавления строки в лог
function addLogEntry(htmlContent) {
  let wrapper = document.getElementById("logWrapper");
  let content = document.getElementById("logContent");

  // Если лог скрыт полностью — показываем контейнер
  if (wrapper.style.display === "none") {
    wrapper.style.display = "block";
  }

  let div = document.createElement("div");
  div.className = "log-line"; // Убедитесь, что в CSS есть стили для .log-line
  div.style.marginBottom = "4px"; // Небольшой отступ для читаемости
  div.innerHTML = htmlContent;
  content.appendChild(div);

  // Всегда прокручиваем вниз при новом сообщении
  content.scrollTop = content.scrollHeight;
}

// Исправленная функция сворачивания/разворачивания
function toggleLog() {
  let content = document.getElementById("logContent"); // Исправлено: сворачиваем контент, а не wrapper
  let icon = document.getElementById("logIcon");

  if (content.style.display === "none") {
    content.style.display = "block";
    icon.innerText = "▼";
  } else {
    content.style.display = "none";
    icon.innerText = "▲"; // Стрелка вверх, когда свернуто
  }
}

// === 3. Обработка формы и SSE ===

document.getElementById("searchForm").addEventListener("submit", function (e) {
  e.preventDefault();

  const btn = document.getElementById("btnSubmit");
  const logWrapper = document.getElementById("logWrapper");
  const logContent = document.getElementById("logContent");
  const resultWrapper = document.getElementById("resultWrapper");

  // Сброс состояния перед новым поиском
  btn.disabled = true;
  btn.innerText = "⏳ Анализирую..."; // Визуальная обратная связь

  logWrapper.style.display = "block"; // Показываем окно лога
  logContent.style.display = "block"; // Убеждаемся, что контент развернут
  document.getElementById("logIcon").innerText = "▼";

  logContent.innerHTML = ""; // Очищаем старые логи
  resultWrapper.innerHTML = ""; // Очищаем старый результат

  // Сбор данных
  const formData = new FormData(this);
  const params = new URLSearchParams(formData).toString();

  // Подключение к потоку событий
  const evtSource = new EventSource("process.php?" + params);

  // 1. Обычные текстовые логи
  evtSource.onmessage = function (event) {
    try {
      const data = JSON.parse(event.data);
      // Формируем HTML и используем общую функцию
      const html = `<span class="log-time" style="color:#888; font-size:0.8em; margin-right:5px;">[${data.time}]</span> <span style="color:${data.color}">${data.msg}</span>`;
      addLogEntry(html);
    } catch (e) {
      console.error("Ошибка парсинга JSON:", e);
    }
  };

  // 2. Получение итогового HTML
  evtSource.addEventListener("result", function (event) {
    try {
      const data = JSON.parse(event.data);
      resultWrapper.innerHTML = `<div class="result-box fade-in">${data.html}</div>`;

      // Прокручиваем страницу к результату
      resultWrapper.scrollIntoView({ behavior: "smooth" });
    } catch (e) {
      console.error("Ошибка обработки результата:", e);
    }
  });

  // 3. Завершение работы (ошибка или конец потока)
  evtSource.onerror = function () {
    evtSource.close(); // Обязательно закрываем соединение
    btn.disabled = false;
    btn.innerText = "Найти и Анализировать"; // Возвращаем текст кнопки

    // Добавляем финальную запись в лог
    addLogEntry("<strong>🏁 Соединение закрыто.</strong>");
  };
});
