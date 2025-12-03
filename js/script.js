// 1. Settings Management (Modal & Cookie)

// Function to open the settings modal
function openSettings() {
  document.getElementById("settingsModal").style.display = "flex";
}

// Function to close the settings modal
function closeSettings() {
  document.getElementById("settingsModal").style.display = "none";
}

// Global click listener to close the modal when clicking outside the content area
window.onclick = function (event) {
  let modal = document.getElementById("settingsModal");
  if (event.target == modal) {
    closeSettings();
  }
};

// Function to save the API Key into a Cookie
function saveApiKey() {
  let key = document.getElementById("apiKeyInput").value.trim();

  if (key.length > 0) {
    // Set the cookie with the following attributes:
    // 1. Name: gemini_user_key
    // 2. encodeURIComponent: Encodes special characters for safety
    // 3. path=/: Available across the whole site
    // 4. max-age: 1 year (in seconds)
    // 5. samesite=strict: CSRF protection
    document.cookie =
      "gemini_user_key=" +
      encodeURIComponent(key) +
      "; path=/; max-age=31536000; samesite=strict Secure";
    alert("Key saved! The page will reload.");
  } else {
    // If empty, delete the cookie by setting max-age to 0
    document.cookie = "gemini_user_key=; path=/; max-age=0";
    alert("Key removed. System key will be used (if available).");
  }
  // Reload the page to apply the new configuration
  location.reload();
}

// 2. UI Logic & Logger (Console Output)

// Helper function to append a new line to the log container
function addLogEntry(htmlContent) {
  let wrapper = document.getElementById("logWrapper");
  let content = document.getElementById("logContent");

  // If the log block is currently hidden, show it
  if (wrapper.style.display === "none") {
    wrapper.style.display = "block";
  }

  let div = document.createElement("div");
  div.className = "log-line";
  div.style.marginBottom = "4px";
  div.innerHTML = htmlContent;
  content.appendChild(div);

  // Auto-scroll: Set scroll position to the bottom to show the latest message
  content.scrollTop = content.scrollHeight;
}

// Function to collapse/expand the log content (Accordion style)
function toggleLog() {
  let content = document.getElementById("logContent");
  let icon = document.getElementById("logIcon");

  // Toggle visibility logic
  if (content.style.display === "none") {
    content.style.display = "block";
    icon.innerText = "▼";
  } else {
    content.style.display = "none";
    icon.innerText = "▲";
  }
}

// 3. Form Handling & SSE (Server-Sent Events)

document.getElementById("searchForm").addEventListener("submit", function (e) {
  e.preventDefault();

  const btn = document.getElementById("btnSubmit");
  const logWrapper = document.getElementById("logWrapper");
  const logContent = document.getElementById("logContent");
  const resultWrapper = document.getElementById("resultWrapper");

  // Disable button to prevent double submission
  btn.disabled = true;
  // Change button text to indicate loading
  btn.innerText = "⏳ Analyzing...";

  // Show log container
  logWrapper.style.display = "block";
  // Ensure log content is expanded
  logContent.style.display = "block";
  // Set arrow icon to down
  document.getElementById("logIcon").innerText = "▼";

  // Clear previous logs
  logContent.innerHTML = "";
  // Clear previous results
  resultWrapper.innerHTML = "";

  // Prepare Data
  // Create FormData object from the form inputs
  const formData = new FormData(this);
  // Convert FormData to URL query string (e.g., query=audi&limit=5)
  const params = new URLSearchParams(formData).toString();

  // Connect to Stream (SSE)
  const evtSource = new EventSource("process.php?" + params);

  // 1. Handle standard log messages (intermediate updates)
  evtSource.onmessage = function (event) {
    try {
      // Parse the incoming JSON data
      const data = JSON.parse(event.data);

      const html = `<span class="log-time" style="color:#888; font-size:0.8em; margin-right:5px;">[${data.time}]</span> <span style="color:${data.color}">${data.msg}</span>`;

      addLogEntry(html);
    } catch (e) {
      console.error("JSON Parsing Error:", e);
    }
  };

  // 2. Handle the specific 'result' event
  // This event contains the final HTML output from the server
  evtSource.addEventListener("result", function (event) {
    try {
      const data = JSON.parse(event.data);

      // Inject the final HTML into the result container
      resultWrapper.innerHTML = `<div class="result-box fade-in">${data.html}</div>`;

      // Smooth scroll to the result section
      resultWrapper.scrollIntoView({ behavior: "smooth" });
    } catch (e) {
      console.error("Result Processing Error:", e);
    }
  });

  // 3. Handle errors or end of stream
  // This triggers when the server closes the connection or a network error occurs
  evtSource.onerror = function () {
    // Close the connection from the client side
    evtSource.close();

    // Re-enable the submit button
    btn.disabled = false;
    // Restore button text
    btn.innerText = "Find and Analyze";

    addLogEntry("<strong>🏁 Connection closed.</strong>");
  };
});
