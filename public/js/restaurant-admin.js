document.addEventListener('DOMContentLoaded', function () {

    /*
    |--------------------------------------------------------------------------
    | Active navigation: highlight the best-matching link and open its group
    |--------------------------------------------------------------------------
    */

    (function markActiveNavigation() {
        const path = window.location.pathname.replace(/\/+$/, '') || '/';
        let best = null;
        let bestLength = -1;

        document.querySelectorAll('.sidebar-navigation a[href]').forEach(function (link) {
            const linkPath = new URL(link.href, window.location.origin).pathname.replace(/\/+$/, '') || '/';
            const matches = path === linkPath || path.startsWith(linkPath + '/');
            if (matches && linkPath.length > bestLength) {
                best = link;
                bestLength = linkPath.length;
            }
        });

        if (!best) {
            return;
        }

        best.classList.add('active');
        best.setAttribute('aria-current', 'page');

        const submenu = best.closest('.sidebar-submenu');
        if (submenu) {
            submenu.classList.add('show');
            const toggle = document.querySelector('[data-sidebar-target="' + submenu.id + '"]');
            toggle?.classList.add('has-active');
        }

        best.scrollIntoView({ block: 'nearest' });
    })();

    /*
    |--------------------------------------------------------------------------
    | Sidebar Groups
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll('[data-sidebar-target]')
        .forEach(function (button) {

            const targetId =
                button.dataset.sidebarTarget;

            const target =
                document.getElementById(targetId);

            if (!target) {
                return;
            }

            if (target.classList.contains('show')) {
                button.classList.add('open');
            }

            button.addEventListener('click', function () {

                target.classList.toggle('show');

                button.classList.toggle(
                    'open',
                    target.classList.contains('show')
                );

            });

        });


    /*
    |--------------------------------------------------------------------------
    | Mobile Sidebar
    |--------------------------------------------------------------------------
    */

    const sidebar =
        document.getElementById('adminSidebar');

    const overlay =
        document.getElementById('sidebarOverlay');

    const openButton =
        document.getElementById('sidebarOpen');

    const closeButton =
        document.getElementById('sidebarClose');


    function openSidebar() {

        sidebar?.classList.add('show');

        overlay?.classList.add('show');
    }


    function closeSidebar() {

        sidebar?.classList.remove('show');

        overlay?.classList.remove('show');
    }


    openButton?.addEventListener(
        'click',
        openSidebar
    );

    closeButton?.addEventListener(
        'click',
        closeSidebar
    );

    overlay?.addEventListener(
        'click',
        closeSidebar
    );


    /*
    |--------------------------------------------------------------------------
    | Accessibility
    |--------------------------------------------------------------------------
    */

    const accessibilityTrigger =
        document.getElementById(
            'accessibilityTrigger'
        );

    const accessibilityPanel =
        document.getElementById(
            'accessibilityPanel'
        );

    const accessibilityClose =
        document.getElementById(
            'accessibilityClose'
        );


    accessibilityTrigger?.addEventListener(
        'click',
        function () {

            accessibilityPanel
                ?.classList
                .toggle('show');

            chatbotPanel
                ?.classList
                .remove('show');
        }
    );


    accessibilityClose?.addEventListener(
        'click',
        function () {

            accessibilityPanel
                ?.classList
                .remove('show');
        }
    );


    document
        .querySelectorAll('[data-accessibility]')
        .forEach(function (button) {

            button.addEventListener(
                'click',
                function () {

                    const action =
                        this.dataset.accessibility;

                    if (action === 'increase') {

                        document.documentElement
                            .classList
                            .remove('access-font-small');

                        document.documentElement
                            .classList
                            .add('access-font-large');
                    }


                    if (action === 'decrease') {

                        document.documentElement
                            .classList
                            .remove('access-font-large');

                        document.documentElement
                            .classList
                            .add('access-font-small');
                    }


                    if (action === 'contrast') {

                        document.body
                            .classList
                            .toggle('access-high-contrast');
                    }


                    if (action === 'grayscale') {

                        document.body
                            .classList
                            .toggle('access-grayscale');
                    }


                    if (action === 'reset') {

                        document.documentElement
                            .classList
                            .remove(
                                'access-font-large',
                                'access-font-small'
                            );

                        document.body
                            .classList
                            .remove(
                                'access-high-contrast',
                                'access-grayscale'
                            );
                    }

                }
            );

        });


    /*
    |--------------------------------------------------------------------------
    | Chatbot
    |--------------------------------------------------------------------------
    */

    const chatbotTrigger =
        document.getElementById(
            'chatbotTrigger'
        );

    const chatbotPanel =
        document.getElementById(
            'chatbotPanel'
        );

    const chatbotClose =
        document.getElementById(
            'chatbotClose'
        );

    const chatbotForm =
        document.getElementById(
            'chatbotForm'
        );

    const chatbotInput =
        document.getElementById(
            'chatbotInput'
        );

    const chatMessages =
        document.getElementById(
            'chatMessages'
        );


    chatbotTrigger?.addEventListener(
        'click',
        function () {

            chatbotPanel
                ?.classList
                .toggle('show');

            accessibilityPanel
                ?.classList
                .remove('show');
        }
    );


    chatbotClose?.addEventListener(
        'click',
        function () {

            chatbotPanel
                ?.classList
                .remove('show');
        }
    );


    // Optional attachment: one photo or PDF per message (max 5 MB).
    const chatbotFile = document.getElementById('chatbotFile');
    const chatbotAttachment = document.getElementById('chatbotAttachment');
    const chatbotAttachmentName = document.getElementById('chatbotAttachmentName');
    let chatbotUpload = null;

    function clearChatbotAttachment() {
        chatbotUpload = null;
        if (chatbotFile) { chatbotFile.value = ''; }
        if (chatbotAttachment) { chatbotAttachment.hidden = true; }
    }

    document.getElementById('chatbotAttach')?.addEventListener('click', function () { chatbotFile?.click(); });
    document.getElementById('chatbotAttachmentRemove')?.addEventListener('click', clearChatbotAttachment);
    chatbotFile?.addEventListener('change', function () {
        const chosen = chatbotFile.files && chatbotFile.files[0];
        if (!chosen) { return; }
        if (chosen.size > 5 * 1024 * 1024) {
            clearChatbotAttachment();
            const warning = document.createElement('div');
            warning.className = 'chat-message bot';
            warning.textContent = 'That file is too large. Please send one under 5 MB.';
            chatMessages.appendChild(warning);
            return;
        }
        chatbotUpload = chosen;
        chatbotAttachmentName.textContent = chosen.name;
        chatbotAttachment.hidden = false;
    });

    chatbotForm?.addEventListener(
        'submit',
        function (event) {

            event.preventDefault();

            const message =
                chatbotInput.value.trim();
            const file = chatbotUpload;

            if (!message && !file) {
                return;
            }


            const userMessage =
                document.createElement('div');

            userMessage.className =
                'chat-message user';

            if (file && file.type.startsWith('image/')) {
                const preview = document.createElement('img');
                preview.src = URL.createObjectURL(file);
                preview.alt = 'Attached photo';
                preview.className = 'chat-message-image';
                userMessage.appendChild(preview);
            } else if (file) {
                const label = document.createElement('div');
                label.className = 'fw-semibold';
                label.textContent = file.name;
                userMessage.appendChild(label);
            }
            if (message) {
                userMessage.appendChild(document.createTextNode(message));
            }

            chatMessages.appendChild(
                userMessage
            );

            chatbotInput.value = '';
            clearChatbotAttachment();

            const body = new FormData();
            if (message) { body.append('message', message); }
            if (file) { body.append('file', file, file.name); }


            const botMessage = document.createElement('div');
            botMessage.className = 'chat-message bot';
            botMessage.textContent = file ? 'Looking at your file...' : '...';
            chatMessages.appendChild(botMessage);
            chatMessages.scrollTop = chatMessages.scrollHeight;

            fetch('/admin/chatbot/ask', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: body
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    const errorText = data.errors ? Object.values(data.errors).flat()[0] : null;
                    let text = errorText || data.reply || 'Sorry, something went wrong.';
                    (data.items || []).forEach(function (item) {
                        text += '\n' + item.name + ', ' + Number(item.price).toLocaleString();
                    });
                    botMessage.textContent = text;
                    botMessage.style.whiteSpace = 'pre-line';
                })
                .catch(function () {
                    botMessage.textContent = 'The assistant is unavailable right now.';
                })
                .finally(function () {
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                });

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Single Flash Message
    |--------------------------------------------------------------------------
    */

    setTimeout(function () {

        document
            .querySelectorAll(
                '.global-flash-message'
            )
            .forEach(function (element) {

                if (
                    typeof bootstrap !== 'undefined'
                ) {
                    bootstrap
                        .Alert
                        .getOrCreateInstance(
                            element
                        )
                        .close();
                }

            });

    }, 5000);

});