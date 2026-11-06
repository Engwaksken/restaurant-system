document.addEventListener('DOMContentLoaded', function () {

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


    chatbotForm?.addEventListener(
        'submit',
        function (event) {

            event.preventDefault();

            const message =
                chatbotInput.value.trim();

            if (!message) {
                return;
            }


            const userMessage =
                document.createElement('div');

            userMessage.className =
                'chat-message user';

            userMessage.textContent =
                message;

            chatMessages.appendChild(
                userMessage
            );

            chatbotInput.value = '';


            setTimeout(function () {

                const botMessage =
                    document.createElement('div');

                botMessage.className =
                    'chat-message bot';

                botMessage.textContent =
                    'Your message has been received.';

                chatMessages.appendChild(
                    botMessage
                );

                chatMessages.scrollTop =
                    chatMessages.scrollHeight;

            }, 250);

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