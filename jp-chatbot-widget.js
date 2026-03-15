(function () {
  "use strict";

  function initJPChatbot() {

    /* ===============================
       1. STATE & CONFIG
    =============================== */

    var state = {
      autoGreetDelay: 3000,
      apiKey: null,
      chatUrl: "",
      visitorId: "",
      initialized: false,
      audioUnlocked: false,
      pendingGreeting: null,
      sound: new Audio(),
      historyLoaded: false,
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
    };

    /* ===============================
       2. DETECT SCRIPT & VISITOR
    =============================== */

    var scripts = document.getElementsByTagName("script");

    for (var i = 0; i < scripts.length; i++) {
      var s = scripts[i];

      if (s.src && s.src.indexOf("jp-chatbot-widget.js") !== -1) {

        state.apiKey = s.getAttribute("data-api-key");

        try {
          var u = new URL(s.src);
          state.chatUrl = u.origin + u.pathname.replace(/\/[^\/]+$/, "") + "/chat.php";
        } catch (e) {
          state.chatUrl = s.src.split("/").slice(0, -1).join("/") + "/chat.php";
        }

        break;
      }
    }

    if (!state.apiKey || !state.chatUrl) return;

    try {

      state.visitorId = localStorage.getItem("jp_visitor_id");

      if (!state.visitorId) {
        state.visitorId = Math.random().toString(36).slice(2);
        localStorage.setItem("jp_visitor_id", state.visitorId);
      }

    } catch (e) {
      state.visitorId = "guest_" + Math.random().toString(36).slice(2, 7);
    }

    /* ===============================
       3. UI & STYLES
    =============================== */

    document.body.insertAdjacentHTML("beforeend", `
      <div id="jp-chat-tooltip" style="display:none"><span id="jp-tooltip-text"></span></div>
      <div id="jp-chat-btn">
        <div id="jp-icon-container" class="jp-icon"></div>
        <div id="jp-chat-badge" style="display:none">1</div>
      </div>
      <div id="jp-chat-box">
        <div class="jp-header">
          <div class="jp-header-left">
            <div class="jp-avatar-wrapper">
              <img id="jp-bot-avatar" src="" alt="">
              <span class="jp-status-dot"></span>
            </div>
            <div class="jp-bot-info">
              <strong id="jp-bot-title">Assistant</strong>
              <small id="jp-bot-desc">Online</small>
            </div>
          </div>
          <button id="jp-close-chat" aria-label="Close">✕</button>
        </div>
        <div id="jp-messages-container"><div id="jp-messages"></div></div>
        <div class="jp-input">
          <input id="jp-input-text" placeholder="Ketik pesan..." autocomplete="off">
          <button id="jp-send">➤</button>
        </div>
      </div>
    `);

    /* ===============================
       CSS (Refined Header) - FIXED SYNTAX
    =============================== */
    var style = document.createElement("style");
    style.textContent = ':root { --jp-primary: #000; }' +
      '#jp-chat-btn { position:fixed; bottom:20px; right:20px; width:65px; height:65px; background:var(--jp-primary); border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; z-index:999999; box-shadow:0 4px 15px rgba(0,0,0,0.3); transition: transform 0.2s; }' +
      '#jp-chat-btn:hover { transform: scale(1.05); }' +
      '.jp-icon { width:100%; height:100%; display:flex; align-items:center; justify-content:center; }' +
      '.jp-icon img { width:75%; height:75%; object-fit:contain; pointer-events:none; }' +
      '#jp-chat-badge { position:absolute; top:-2px; right:-2px; background:red; color:white; width:22px; height:22px; border-radius:50%; display:none; align-items:center; justify-content:center; font-size:11px; font-weight:bold; border:2px solid #fff; animation: jp-bounce 2s infinite; }' +

      /* Perbaikan tanda petik pada properti content */
      '#jp-chat-tooltip { position: fixed; bottom: 95px; right: 20px; background: #fff; color: #333; padding: 10px 15px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 1px solid #eee; font-family: system-ui, sans-serif; font-size: 13px; max-width: 220px; line-height: 1.4; animation: jp-fade-in 0.4s ease-out; cursor: pointer; z-index: 999998; }' +
      '#jp-chat-tooltip::after { content: ""; position: absolute; bottom: -8px; right: 26px; border-left: 8px solid transparent; border-right: 8px solid transparent; border-top: 8px solid #fff; }' +
      
      '@keyframes jp-fade-in { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }' +
      '@keyframes jp-bounce { 0%,20%,50%,80%,100% {transform: translateY(0);} 40% {transform: translateY(-5px);} }' +
      
      '#jp-chat-box { position:fixed; bottom:95px; right:20px; width:330px; height:480px; background:#fff; border-radius:15px; box-shadow:0 10px 40px rgba(0,0,0,0.2); display:none; flex-direction:column; z-index:999999; border:1px solid #eee; font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; overflow:hidden; }' +
      '.jp-header { background:var(--jp-primary); color:#fff; padding:12px 15px; display:flex; align-items:center; justify-content:space-between; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }' +
      '.jp-header-left { display:flex; align-items:center; flex: 1; }' +
      '.jp-avatar-wrapper { position:relative; width:40px; height:40px; flex-shrink: 0; }' +
      '#jp-bot-avatar { width:100%; height:100%; border-radius:50%; background:#fff; object-fit:cover; border:2px solid rgba(255,255,255,0.2); }' +
      '.jp-status-dot { position:absolute; bottom:1px; right:1px; width:10px; height:10px; background:#4CAF50; border-radius:50%; border:2px solid var(--jp-primary); }' +
      '.jp-bot-info { display:flex; flex-direction:column; margin-left: 12px; }' +
      '#jp-bot-title { font-size:14px; font-weight:600; line-height:1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }' +
      '#jp-bot-desc { font-size:11px; opacity:.8; }' +
      '#jp-close-chat { background:none; border:none; color:#fff; cursor:pointer; font-size:20px; opacity:0.6; padding: 5px; transition:0.2s; flex-shrink: 0; }' +
      '#jp-close-chat:hover { opacity:1; transform: scale(1.1); }' +

      '#jp-messages-container { flex:1; overflow-y:auto; background:#f4f7f6; }' +
      '#jp-messages { padding:15px; display:flex; flex-direction:column; gap:10px; }' +
      '.jp-user, .jp-bot { max-width:85%; padding:10px 14px; border-radius:15px; font-size:14px; line-height:1.4; }' +
      '.jp-user { align-self:flex-end; background:var(--jp-primary); color:#fff; border-radius:15px 15px 0 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }' +
      '.jp-bot { align-self:flex-start; background:#fff; border:1px solid #ececec; border-radius:15px 15px 15px 0; color:#333; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }' +
      
      '.jp-input { display:flex; padding:12px; border-top:1px solid #eee; background:#fff; }' +
      '.jp-input input { flex:1; padding:10px 15px; border:1px solid #eee; border-radius:20px; outline:none; font-size:14px; background:#f9f9f9; }' +
      '.jp-input input:focus { border-color:var(--jp-primary); background:#fff; }' +
      '.jp-send { background:none; border:none; color:var(--jp-primary); font-size:18px; cursor:pointer; padding-left:10px; transition: transform 0.2s; }' +
      '.jp-send:hover { transform: scale(1.1); }';
    document.head.appendChild(style);

    /* ===============================
       4. HELPERS
    =============================== */

    var ui = {
      box: document.getElementById("jp-chat-box"),
      badge: document.getElementById("jp-chat-badge"),
      tooltip: document.getElementById("jp-chat-tooltip"),
      tText: document.getElementById("jp-tooltip-text"),
      msgDiv: document.getElementById("jp-messages"),
      msgCont: document.getElementById("jp-messages-container"),
      input: document.getElementById("jp-input-text")
    };

    function isOpen() {
      return ui.box.style.display === "flex";
    }

    function render(role, text) {

      if (!text) return;

      var d = document.createElement("div");

      d.className = (role === "user") ? "jp-user" : "jp-bot";
      d.textContent = text;

      ui.msgDiv.appendChild(d);

      ui.msgCont.scrollTop = ui.msgCont.scrollHeight;
    }

    function renderHistory(history) {

      if (!Array.isArray(history)) return;

      history.forEach(function (m) {

        render(
          m.role === "assistant" ? "bot" : "user",
          m.message
        );

      });

      state.historyLoaded = true;
    }

    function showBadge() {

      if (isOpen()) return;

      ui.badge.style.display = "flex";

      if (state.audioUnlocked && state.sound.src) {

        state.sound.currentTime = 0;

        state.sound.play().catch(function () { });

      }
    }

    function showTooltip(text) {

      if (isOpen()) return;

      ui.tText.textContent = text;

      ui.tooltip.style.display = "block";

      setTimeout(function () {

        ui.tooltip.style.display = "none";

      }, 7000);
    }

    /* ===============================
       5. CONFIG
    =============================== */

    function applyConfig(cfg) {

      document.getElementById("jp-bot-title").textContent =
        cfg.bot_name || "Assistant";

      document.getElementById("jp-bot-desc").textContent =
        cfg.bot_desc || "Online";

      document.getElementById("jp-bot-avatar").src =
        cfg.bot_avatar || "";

      if (cfg.widget_icon) {

        document.getElementById("jp-icon-container").innerHTML =
          '<img src="' + cfg.widget_icon + '" alt="icon">';

      }

      document.documentElement.style.setProperty(
        "--jp-primary",
        cfg.widget_background || "#000"
      );

      var notif = cfg.notif || {};

      if (notif.sound && notif.sound_url) {

        state.sound.src = notif.sound_url;

        state.sound.load();

      }

      if (notif.popup && !sessionStorage.getItem("jp_session_opened")) {

        showTooltip(notif.popup_text || "Halo 👋");

      }
    }

    /* ===============================
       6. API
    =============================== */

    function apiCall(msg) {

      return fetch(state.chatUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-API-KEY": state.apiKey
        },
        body: JSON.stringify({
          visitor_id: state.visitorId,
          message: msg,
          timezone: state.timezone
        })
      })
        .then(function (r) { return r.json(); });
    }

    function loadChat() {

      if (state.initialized) return;

      state.initialized = true;

      apiCall("__load__").then(function (d) {

        if (d.config) applyConfig(d.config);

        if (d.history && !state.historyLoaded) {

          renderHistory(d.history);

        }

        if (d.reply) {

          render("bot", d.reply);

        }

      });

    }

    /* ===============================
       7. CHAT TOGGLE
    =============================== */

    function toggleChat() {

      var open = !isOpen();

      ui.box.style.display = open ? "flex" : "none";

      if (open) {

        ui.tooltip.style.display = "none";

        ui.badge.style.display = "none";

        sessionStorage.setItem("jp_session_opened", "1");

        loadChat();

      }

    }

    /* ===============================
       8. SEND MESSAGE
    =============================== */

    function sendMessage() {

      var val = ui.input.value.trim();

      if (!val) return;

      ui.input.value = "";

      render("user", val);

      apiCall(val).then(function (d) {

        if (d.reply) {

          render("bot", d.reply);

        }

      });

    }

    /* ===============================
       9. EVENTS
    =============================== */

    document.getElementById("jp-chat-btn").onclick = toggleChat;
    document.getElementById("jp-close-chat").onclick = toggleChat;
    ui.tooltip.onclick = toggleChat;

    document.getElementById("jp-send").onclick = sendMessage;

    ui.input.onkeydown = function (e) {

      if (e.key === "Enter") sendMessage();

    };

    /* ===============================
       10. AUDIO UNLOCK
    =============================== */

    ["click", "touchstart"].forEach(function (evt) {

      document.addEventListener(evt, function () {

        if (state.audioUnlocked) return;

        state.sound.muted = true;

        state.sound.play().then(function () {

          state.sound.pause();

          state.sound.currentTime = 0;

          state.sound.muted = false;

          state.audioUnlocked = true;

        }).catch(function () { });

      }, { once: true });

    });

    /* ===============================
       11. AUTO INIT
    =============================== */

    setTimeout(function () {

      if (!state.initialized) {

        apiCall("__load__").then(function (d) {

          if (d.config) applyConfig(d.config);

          if (d.reply) {

            state.pendingGreeting = d.reply;

            if (!sessionStorage.getItem("jp_session_opened")) {

              showTooltip(d.reply);

            } else {

              showBadge();

            }

          }

        });

      }

    }, state.autoGreetDelay);

  }

  if (document.readyState === "complete") initJPChatbot();
  else window.addEventListener("load", initJPChatbot);

})();