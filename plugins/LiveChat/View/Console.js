(function () {
    const state = {
        status: "",
        sessions: [],
        activeId: 0,
        pollTimer: null,
        activeMessageSignature: "",
        loadingSessions: false,
        messageRequestId: 0,
        baseTitle: document.title || "在线客服"
    };

    const api = {
        sessions: "/plugin/LiveChat/console/sessions",
        messages: "/plugin/LiveChat/console/messages",
        reply: "/plugin/LiveChat/console/reply",
        end: "/plugin/LiveChat/console/end"
    };

    const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || "";

    const parseJsonResponse = async response => {
        const contentType = response.headers.get("content-type") || "";
        const text = await response.text();
        if (!contentType.includes("application/json")) {
            throw new Error("接口返回非JSON，请检查插件安装与服务端日志");
        }
        try {
            return JSON.parse(text);
        } catch (error) {
            throw new Error("接口返回的JSON格式无效，请检查插件安装与服务端日志");
        }
    };

    const post = (url, data) => fetch(url, {
        method: "POST",
        credentials: "same-origin",
        headers: {"Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"},
        body: new URLSearchParams({
            ...data,
            _csrf_token: getCsrfToken()
        })
    }).then(parseJsonResponse);

    const safeSender = sender => ["visitor", "admin", "system"].includes(sender) ? sender : "system";

    const esc = text => String(text ?? "").replace(/[&<>"']/g, char => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        "\"": "&quot;",
        "'": "&#039;"
    })[char]);

    const listEl = document.querySelector(".lc-session-list");
    const emptyEl = document.querySelector(".lc-chat-empty");
    const activeEl = document.querySelector(".lc-chat-active");
    const messagesEl = document.querySelector(".lc-messages");
    const replyText = document.querySelector(".lc-reply textarea");
    const replyButton = document.querySelector(".lc-reply button");
    const endButton = document.querySelector(".lc-end-session");

    const activeSession = () => state.sessions.find(item => Number(item.id) === Number(state.activeId));

    const messageSignature = (session, messages) => JSON.stringify([
        Number(session.id || 0),
        Number(session.status || 0),
        String(session.visitor_name || ""),
        String(session.status_text || ""),
        String(session.client_ip || ""),
        (messages || []).map(message => [
            Number(message.id || 0),
            String(message.sender || ""),
            String(message.content || "")
        ])
    ]);

    const unreadTotal = () => state.sessions.reduce((total, item) => total + Math.max(0, Number(item.unread) || 0), 0);

    const updateUnreadTitle = () => {
        const total = unreadTotal();
        document.title = total > 0 ? `(${total}) ${state.baseTitle}` : state.baseTitle;
    };

    const renderSessions = () => {
        const previousScrollTop = listEl.scrollTop;
        updateUnreadTitle();
        if (!state.sessions.length) {
            listEl.innerHTML = '<div class="lc-session-item"><div class="lc-session-preview">暂无会话</div></div>';
            return;
        }

        listEl.innerHTML = state.sessions.map(item => {
            const id = Number(item.id);
            const unread = Math.max(0, Number(item.unread) || 0);
            const hasUnread = unread > 0;
            const active = id === Number(state.activeId);
            return `
                <div class="lc-session-item ${active ? "active" : ""} ${hasUnread ? "has-unread" : ""}" data-id="${id}">
                    <div class="lc-session-row">
                        <div class="lc-session-identity">
                            ${hasUnread ? '<span class="lc-unread-dot" aria-hidden="true"></span>' : ""}
                            <div class="lc-session-name">${esc(item.visitor_name || ("访客-" + id))}</div>
                        </div>
                        ${hasUnread ? `<span class="lc-session-badge">${unread > 99 ? "99+" : unread}</span>` : ""}
                    </div>
                    <div class="lc-session-preview-row">
                        ${hasUnread ? '<span class="lc-unread-label">新回复</span>' : ""}
                        <div class="lc-session-preview">${esc(item.last_message || "暂无消息")}</div>
                    </div>
                    <div class="lc-session-time">${esc(item.status_text)} · ${esc(item.last_msg_at || item.create_time)}</div>
                </div>
            `;
        }).join("");
        listEl.scrollTop = previousScrollTop;
    };

    const renderMessages = (session, messages, options = {}) => {
        const closed = Number(session.status) === 1;
        const messagesList = messages || [];
        const signature = messageSignature(session, messagesList);
        const previousScrollTop = messagesEl.scrollTop;
        const distanceFromBottom = messagesEl.scrollHeight - messagesEl.scrollTop - messagesEl.clientHeight;
        const shouldStickToBottom = options.forceScroll || distanceFromBottom < 40;
        emptyEl.classList.add("hidden");
        activeEl.classList.remove("hidden");
        document.querySelector(".lc-session-title").textContent = session.visitor_name || `访客-${session.id}`;
        document.querySelector(".lc-session-meta").textContent = `#${session.id} · ${session.status_text} · ${session.client_ip || "-"}`;
        replyText.disabled = closed;
        replyButton.disabled = closed;
        endButton.disabled = closed;
        endButton.classList.toggle("hidden", closed);
        replyText.placeholder = closed ? "会话已结束，不能回复" : "输入回复内容";
        replyButton.textContent = closed ? "已结束" : "发送";

        if (state.activeMessageSignature === signature) {
            if (shouldStickToBottom) {
                messagesEl.scrollTop = messagesEl.scrollHeight;
            }
            return;
        }

        messagesEl.innerHTML = messagesList.map(message => {
            const sender = safeSender(String(message.sender || ""));
            return `<div class="lc-message ${sender}">${esc(message.content)}</div>`;
        }).join("");
        state.activeMessageSignature = signature;
        messagesEl.scrollTop = shouldStickToBottom ? messagesEl.scrollHeight : previousScrollTop;
    };

    const loadSessions = (options = {}) => {
        const background = Boolean(options.background);
        if (state.loadingSessions) return Promise.resolve();
        state.loadingSessions = true;

        return post(api.sessions, {status: state.status}).then(res => {
            if (res.code !== 200) throw new Error(res.msg || "加载会话失败");
            state.sessions = res.data.list || [];
            renderSessions();
            if (state.activeId && activeSession()) {
                return loadMessages(state.activeId, false, {background});
            }
        }).catch(error => {
            if (!background || !state.sessions.length) {
                listEl.innerHTML = `<div class="lc-session-item"><div class="lc-session-preview">${esc(error.message)}</div></div>`;
            }
        }).finally(() => {
            state.loadingSessions = false;
        });
    };

    const loadMessages = (id, updateActive = true, options = {}) => {
        const background = Boolean(options.background);
        const requestId = ++state.messageRequestId;

        return post(api.messages, {session_id: id}).then(res => {
            if (requestId !== state.messageRequestId) return;
            if (res.code !== 200) throw new Error(res.msg || "加载消息失败");
            if (updateActive) state.activeId = Number(id);
            const session = activeSession();
            if (session) session.unread = 0;
            renderMessages(res.data.session, res.data.messages, {forceScroll: updateActive});
            renderSessions();
        }).catch(error => {
            if (requestId !== state.messageRequestId) return;
            if (!background || !state.activeMessageSignature) {
                messagesEl.innerHTML = `<div class="lc-message system">${esc(error.message)}</div>`;
                state.activeMessageSignature = "";
            }
        });
    };

    const reply = () => {
        const content = replyText.value.trim();
        if (!content || !state.activeId) return;
        state.messageRequestId += 1;
        replyButton.disabled = true;
        post(api.reply, {session_id: state.activeId, content}).then(res => {
            if (res.code !== 200) throw new Error(res.msg || "回复失败");
            replyText.value = "";
            renderMessages(res.data.session, res.data.messages, {forceScroll: true});
            loadSessions();
        }).catch(error => {
            alert(error.message || "回复失败");
        }).finally(() => {
            const session = activeSession();
            replyButton.disabled = session ? Number(session.status) === 1 : false;
            if (!replyText.disabled) replyText.focus();
        });
    };

    const endSession = () => {
        if (!state.activeId) return;
        if (!window.confirm("确定结束该会话吗？历史消息会保留。")) return;
        state.messageRequestId += 1;
        post(api.end, {session_id: state.activeId}).then(res => {
            if (res.code !== 200) throw new Error(res.msg || "结束失败");
            renderMessages(res.data.session, res.data.messages, {forceScroll: true});
            loadSessions();
        }).catch(error => {
            alert(error.message || "结束失败");
        });
    };

    listEl.addEventListener("click", event => {
        const item = event.target.closest(".lc-session-item[data-id]");
        if (!item) return;
        loadMessages(Number(item.dataset.id));
    });

    document.querySelector(".lc-refresh").addEventListener("click", loadSessions);
    document.querySelectorAll(".lc-filter button").forEach(button => {
        button.addEventListener("click", () => {
            document.querySelectorAll(".lc-filter button").forEach(item => item.classList.remove("active"));
            button.classList.add("active");
            state.status = button.dataset.status;
            state.activeId = 0;
            state.activeMessageSignature = "";
            activeEl.classList.add("hidden");
            emptyEl.classList.remove("hidden");
            loadSessions();
        });
    });
    replyButton.addEventListener("click", reply);
    endButton.addEventListener("click", endSession);
    replyText.addEventListener("keydown", event => {
        if (event.key === "Enter" && !event.shiftKey && !event.isComposing && event.keyCode !== 229) {
            event.preventDefault();
            reply();
        }
    });

    loadSessions();
    state.pollTimer = window.setInterval(() => loadSessions({background: true}), 5000);
})();
