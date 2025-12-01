// Открыть окно
function openSettings() {
  document.getElementById("settingsModal").style.display = "flex";
}

// Закрыть окно
function closeSettings() {
  document.getElementById("settingsModal").style.display = "none";
}

// Закрыть по клику вне окна
window.onclick = function (event) {
  let modal = document.getElementById("settingsModal");
  if (event.target == modal) {
    closeSettings();
  }
};

// Сохранить ключ в Куки (на 1 год)
function saveApiKey() {
  let key = document.getElementById("apiKeyInput").value.trim();

  if (key.length > 0) {
    // Устанавливаем куку
    document.cookie =
      "gemini_user_key=" +
      encodeURIComponent(key) +
      "; path=/; max-age=31536000; samesite=strict";
    alert("Ключ сохранен! Страница будет перезагружена.");
  } else {
    // Если поле очистили — удаляем куку
    document.cookie = "gemini_user_key=; path=/; max-age=0";
    alert("Ключ удален. Будет использован системный ключ (если есть).");
  }
  location.reload();
}
