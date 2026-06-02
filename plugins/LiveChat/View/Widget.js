(function () {
    const root = document.getElementById("livechat-root");
    if (!root || root.dataset.ready === "1") {
        return;
    }
    root.dataset.ready = "1";

    const state = {
        lastId: 0,
        open: localStorage.getItem("livechat_window_closed") === "0",
        ended: false,
        bootstrapped: false,
        pollTimer: null,
        pollInterval: Math.max(2, Number(root.dataset.pollInterval || 4)) * 1000
    };

    const api = {
        bootstrap: "/plugin/LiveChat/client/bootstrap",
        send: "/plugin/LiveChat/client/send",
        poll: "/plugin/LiveChat/client/poll",
        end: "/plugin/LiveChat/client/end"
    };

    const defaultCategories = {
        order: "订单/支付问题",
        delivery: "发货/卡密问题",
        account: "账号/使用问题",
        other: "其他咨询"
    };

    const esc = text => String(text ?? "").replace(/[&<>"']/g, char => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        "\"": "&quot;",
        "'": "&#039;"
    })[char]);

    const parseCategories = () => {
        try {
            const parsed = JSON.parse(root.dataset.intakeCategories || "{}");
            return parsed && typeof parsed === "object" && Object.keys(parsed).length ? parsed : defaultCategories;
        } catch (error) {
            return defaultCategories;
        }
    };

    const categories = parseCategories();
    const categoryOptions = Object.entries(categories)
        .map(([value, label]) => `<option value="${esc(value)}">${esc(label)}</option>`)
        .join("");

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

    root.innerHTML = `
        <button class="lc-launcher" type="button" aria-label="打开在线客服">
            <span class="lc-launcher-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M5 8.5C5 5.8 7.2 3.7 10 3.7h4c2.8 0 5 2.1 5 4.8v2.7c0 2.7-2.2 4.8-5 4.8h-2.2l-3.4 2.5c-.6.4-1.4 0-1.4-.7v-2.1A4.7 4.7 0 0 1 5 11.2V8.5Z" fill="currentColor"/>
                </svg>
            </span>
            <span>在线客服</span>
        </button>
        <section class="lc-widget ${state.open ? "" : "lc-hidden"}" aria-live="polite">
            <header class="lc-header">
                <div class="lc-brand">
                    <span class="lc-status-dot" aria-hidden="true"></span>
                    <div class="lc-brand-text">
                        <div class="lc-title">${esc(root.dataset.title || "在线客服")}</div>
                        <div class="lc-subtitle">提问后等待客服回复即可</div>
                    </div>
                </div>
                <div class="lc-actions">
                    <button class="lc-end-session lc-hidden" type="button">结束会话</button>
                    <button class="lc-minimize" type="button" aria-label="最小化" title="最小化">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M6 12h12" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
            </header>

            <main class="lc-view lc-view-intro">
                <div class="lc-intro-panel">
                    <div class="lc-support-mark" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M5 8.8c0-2.5 2-4.5 4.5-4.5h5c2.5 0 4.5 2 4.5 4.5v2.9c0 2.5-2 4.5-4.5 4.5h-2.1l-3.3 2.5c-.6.4-1.4 0-1.4-.7v-2A4.5 4.5 0 0 1 5 11.7V8.8Z" fill="currentColor"/>
                            <path d="M9 10h6M9 13h3.7" stroke="#fff" stroke-width="1.7" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="lc-intro-copy">
                        <div class="lc-kicker">在线咨询</div>
                        <h2>联系客服</h2>
                    </div>
                    <button class="lc-start-button" type="button">
                        <span>开始咨询</span>
                    </button>
                </div>
            </main>

            <main class="lc-view lc-view-intake lc-hidden">
                <form class="lc-intake-form">
                    <label>
                        <span>分类 <b>必填</b></span>
                        <select name="category" required>
                            <option value="">请选择咨询分类</option>
                            ${categoryOptions}
                        </select>
                    </label>
                    <label>
                        <span>邮箱 <b>必填</b></span>
                        <input name="email" type="email" maxlength="120" placeholder="请填写真实邮箱" autocomplete="email" required>
                    </label>
                    <label>
                        <span>QQ</span>
                        <input name="qq" type="text" maxlength="20" inputmode="numeric" placeholder="可选">
                    </label>
                    <label>
                        <span>订单号</span>
                        <input name="order_no" type="text" maxlength="80" placeholder="没有请留空">
                    </label>
                    <label>
                        <span>消息 <b>必填</b></span>
                        <textarea name="message" maxlength="1000" placeholder="请输入您的消息" required></textarea>
                    </label>
                    <div class="lc-intake-error lc-hidden"></div>
                    <button class="lc-primary" type="submit">开始会话</button>
                </form>
            </main>

            <main class="lc-view lc-view-chat lc-hidden">
                <div class="lc-body"></div>
                <div class="lc-ended lc-hidden">会话已结束。</div>
                <footer class="lc-footer">
                    <textarea placeholder="请输入消息"></textarea>
                    <button type="button">发送</button>
                </footer>
            </main>

            <main class="lc-view lc-view-ended lc-hidden">
                <div class="lc-ended-panel">
                    <strong>会话已结束</strong>
                    <span>您可以重新发起咨询。</span>
                    <button class="lc-new-session" type="button">开始新的会话</button>
                </div>
            </main>
        </section>
    `;

    const launcher = root.querySelector(".lc-launcher");
    const widget = root.querySelector(".lc-widget");
    const views = {
        intro: root.querySelector(".lc-view-intro"),
        intake: root.querySelector(".lc-view-intake"),
        chat: root.querySelector(".lc-view-chat"),
        ended: root.querySelector(".lc-view-ended")
    };
    const body = root.querySelector(".lc-body");
    const intakeForm = root.querySelector(".lc-intake-form");
    const intakeError = root.querySelector(".lc-intake-error");
    const chatTextarea = root.querySelector(".lc-footer textarea");
    const sendButton = root.querySelector(".lc-footer button");
    const endButton = root.querySelector(".lc-end-session");
    const endedBar = root.querySelector(".lc-ended");

    const setOpen = open => {
        state.open = open;
        widget.classList.toggle("lc-hidden", !open);
        launcher.classList.toggle("lc-launcher-open", open);
        localStorage.setItem("livechat_window_closed", open ? "0" : "1");
    };

    const setView = view => {
        Object.entries(views).forEach(([name, node]) => {
            node.classList.toggle("lc-hidden", name !== view);
        });
        if (views[view]) {
            views[view].scrollTop = 0;
        }
        endButton.classList.toggle("lc-hidden", view !== "chat");
    };

    const startPolling = () => {
        window.clearInterval(state.pollTimer);
        state.pollTimer = window.setInterval(poll, state.pollInterval);
    };

    const showIntakeError = message => {
        intakeError.textContent = message || "";
        intakeError.classList.toggle("lc-hidden", !message);
    };

    const setIntakeBusy = busy => {
        intakeForm.querySelector(".lc-primary").disabled = busy;
    };

    const renderIntro = () => {
        state.ended = false;
        state.bootstrapped = false;
        state.lastId = 0;
        body.innerHTML = "";
        setView("intro");
    };

    const renderChat = () => {
        setView("chat");
        endedBar.classList.add("lc-hidden");
        chatTextarea.disabled = false;
        sendButton.disabled = false;
        endButton.disabled = false;
    };

    const renderEnded = () => {
        state.ended = true;
        state.bootstrapped = false;
        state.lastId = 0;
        window.clearInterval(state.pollTimer);
        body.innerHTML = "";
        setView("ended");
    };

    const applySession = session => {
        if (!session) return true;
        if (Number(session.status) === 1) {
            renderEnded();
            return false;
        }

        state.ended = false;
        return true;
    };

    const appendMessages = messages => {
        if (state.ended) return;
        (messages || []).forEach(message => {
            const messageId = Number(message.id || 0);
            if (messageId > 0 && messageId <= state.lastId) return;
            if (messageId > 0) state.lastId = messageId;

            const sender = ["visitor", "admin", "system"].includes(message.sender) ? message.sender : "system";
            const node = document.createElement("div");
            node.className = `lc-message ${sender}`;
            node.textContent = String(message.content || "");
            body.appendChild(node);
        });
        body.scrollTop = body.scrollHeight;
    };

    const bootstrap = intake => post(api.bootstrap, intake).then(res => {
        if (res.code !== 200) throw new Error(res.msg || "初始化失败");
        state.bootstrapped = true;
        renderChat();
        if (!applySession(res.data.session)) return;
        appendMessages(res.data.messages);
        startPolling();
    });

    const restoreSession = () => {
        if (state.bootstrapped) return;
        bootstrap({}).catch(() => {
            renderIntro();
        });
    };

    const poll = () => {
        if (!state.bootstrapped || state.ended) return;
        post(api.poll, {after_id: state.lastId}).then(res => {
            if (res.code === 200 && applySession(res.data.session)) {
                appendMessages(res.data.messages);
            }
        }).catch(() => {});
    };

    const send = () => {
        const content = chatTextarea.value.trim();
        if (!content || state.ended || !state.bootstrapped) return;
        sendButton.disabled = true;
        post(api.send, {content}).then(res => {
            if (res.code !== 200) throw new Error(res.msg || "发送失败");
            chatTextarea.value = "";
            if (applySession(res.data.session)) {
                appendMessages(res.data.messages);
            }
        }).catch(error => {
            appendMessages([{sender: "system", content: error.message || "发送失败"}]);
        }).finally(() => {
            sendButton.disabled = state.ended;
        });
    };

    const endSession = () => {
        if (state.ended || !state.bootstrapped) return;
        if (!window.confirm("确定结束当前会话吗？结束后将不能继续发送消息。")) return;
        post(api.end, {}).then(res => {
            if (res.code !== 200) throw new Error(res.msg || "结束失败");
            renderEnded();
        }).catch(error => {
            appendMessages([{sender: "system", content: error.message || "结束失败"}]);
        });
    };

    const collectIntake = () => {
        const data = new FormData(intakeForm);
        return {
            category: String(data.get("category") || "").trim(),
            email: String(data.get("email") || "").trim(),
            qq: String(data.get("qq") || "").trim(),
            order_no: String(data.get("order_no") || "").trim(),
            message: String(data.get("message") || "").trim()
        };
    };

    const validateIntake = intake => {
        if (!intake.category || !Object.prototype.hasOwnProperty.call(categories, intake.category)) return "请选择咨询分类";
        if (!intake.email || intake.email.length > 120 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(intake.email)) return "请填写正确的邮箱";
        if (intake.qq && !/^\d{4,20}$/.test(intake.qq)) return "QQ格式不正确";
        if (intake.order_no.length > 80) return "订单号格式不正确";
        if (!intake.message) return "请输入您的消息";
        if (intake.message.length > 1000) return "消息内容不能超过1000个字符";
        return "";
    };

    launcher.addEventListener("click", () => {
        setOpen(true);
        restoreSession();
    });

    root.querySelector(".lc-minimize").addEventListener("click", () => setOpen(false));
    root.querySelector(".lc-start-button").addEventListener("click", () => {
        showIntakeError("");
        setView("intake");
    });
    root.querySelector(".lc-new-session").addEventListener("click", renderIntro);
    endButton.addEventListener("click", endSession);
    sendButton.addEventListener("click", send);
    chatTextarea.addEventListener("keydown", event => {
        if (event.key === "Enter" && !event.shiftKey) {
            event.preventDefault();
            send();
        }
    });
    intakeForm.addEventListener("submit", event => {
        event.preventDefault();
        const intake = collectIntake();
        const error = validateIntake(intake);
        showIntakeError(error);
        if (error) return;

        setIntakeBusy(true);
        bootstrap(intake).catch(error => {
            showIntakeError(error.message || "会话创建失败");
        }).finally(() => {
            setIntakeBusy(false);
        });
    });

    if (state.open) {
        setOpen(true);
        restoreSession();
    }
})();
