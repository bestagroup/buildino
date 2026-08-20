(() => {
    "use strict";

    const root = document.documentElement;
    const body = document.body;

    const storage = {
        get(key, fallback = null) {
            try {
                return (
                    localStorage.getItem(key)
                    ?? fallback
                );
            } catch {
                return fallback;
            }
        },

        set(key, value) {
            try {
                localStorage.setItem(
                    key,
                    value
                );
            } catch {
                // Storage is optional.
            }
        },
    };

    const toFaDigits = (value) =>
        String(value ?? "")
            .replace(
                /\d/g,
                (digit) =>
                    "۰۱۲۳۴۵۶۷۸۹"[
                        Number(digit)
                    ]
            );

    const formatNumber =
        new Intl.NumberFormat(
            "fa-IR"
        );

    const formatDate =
        new Intl.DateTimeFormat(
            "fa-IR-u-ca-persian",
            {
                year: "numeric",
                month: "2-digit",
                day: "2-digit",
            }
        );

    const formatDateTime =
        new Intl.DateTimeFormat(
            "fa-IR-u-ca-persian",
            {
                year: "numeric",
                month: "2-digit",
                day: "2-digit",
                hour: "2-digit",
                minute: "2-digit",
            }
        );

    const jDateFormat = (
        value,
        includeTime = false
    ) => {
        const date =
            value instanceof Date
                ? value
                : new Date(value);

        if (
            Number.isNaN(
                date.getTime()
            )
        ) {
            return null;
        }

        if (
            typeof window.JDate
            === "function"
        ) {
            try {
                const jalali =
                    new window.JDate(
                        date,
                        {
                            persianNumerical:
                                true,
                        }
                    );

                const datePart =
                    jalali.format(
                        "YYYY/MM/DD"
                    );

                if (! includeTime) {
                    return datePart;
                }

                const timePart =
                    new Intl.DateTimeFormat(
                        "fa-IR",
                        {
                            hour: "2-digit",
                            minute: "2-digit",
                            hour12: false,
                        }
                    ).format(date);

                return `${datePart} - ${timePart}`;
            } catch {
                // Intl below remains a reliable fallback.
            }
        }

        return includeTime
            ? formatDateTime.format(
                date
            )
            : formatDate.format(
                date
            );
    };

    const sharedUi =
        window.BuildinoUI
        && typeof window.BuildinoUI === "object"
            ? window.BuildinoUI
            : {};

    window.BuildinoUI = Object.freeze({
        ...sharedUi,
        toFaDigits,

        number(value) {
            const number =
                Number(value);

            return Number.isFinite(
                number
            )
                ? formatNumber.format(
                    number
                )
                : String(
                    value ?? ""
                );
        },

        date(value) {
            if (! value) {
                return "—";
            }

            return jDateFormat(
                value,
                false
            ) ?? String(value);
        },

        dateTime(value) {
            if (! value) {
                return "—";
            }

            return jDateFormat(
                value,
                true
            ) ?? String(value);
        },

        toast(
            message,
            tone = "success"
        ) {
            if (
                window.Swal
                && typeof window.Swal.fire
                    === "function"
            ) {
                const icon =
                    {
                        danger: "error",
                        warning: "warning",
                        info: "info",
                        success: "success",
                    }[tone]
                    || "success";

                return window.Swal.fire({
                    toast: true,
                    position: "top-start",
                    icon,
                    title: String(
                        message ?? ""
                    ),
                    showConfirmButton:
                        false,
                    timer: 3200,
                    timerProgressBar:
                        true,
                    customClass: {
                        popup:
                            "buildino-swal-toast",
                    },
                });
            }

            return null;
        },
    });

    /* ---------------------------------------------------------------
       Theme
    ---------------------------------------------------------------- */

    const savedTheme =
        storage.get(
            "buildino-theme"
        );

    const systemDark =
        window.matchMedia
        && window.matchMedia(
            "(prefers-color-scheme: dark)"
        ).matches;

    root.dataset.theme =
        savedTheme === "dark"
        || savedTheme === "light"
            ? savedTheme
            : (
                systemDark
                    ? "dark"
                    : "light"
            );

    root.setAttribute(
        "data-bs-theme",
        root.dataset.theme
    );

    document
        .getElementById(
            "themeToggle"
        )
        ?.addEventListener(
            "click",
            () => {
                const next =
                    root.dataset
                        .theme
                    === "dark"
                        ? "light"
                        : "dark";

                root.dataset.theme =
                    next;

                root.setAttribute(
                    "data-bs-theme",
                    next
                );

                storage.set(
                    "buildino-theme",
                    next
                );
            }
        );

    /* ---------------------------------------------------------------
       Sidebar
    ---------------------------------------------------------------- */

    const sidebarToggle =
        document.getElementById(
            "sidebarToggle"
        );

    const sidebarBackdrop =
        document.getElementById(
            "sidebarBackdrop"
        );

    const sidebarCollapse =
        document.getElementById(
            "sidebarCollapse"
        );

    const closeMobileSidebar =
        () => {
            body.classList.remove(
                "sidebar-open"
            );
        };

    sidebarToggle
        ?.addEventListener(
            "click",
            () => {
                body.classList.toggle(
                    "sidebar-open"
                );
            }
        );

    sidebarBackdrop
        ?.addEventListener(
            "click",
            closeMobileSidebar
        );

    if (
        storage.get(
            "buildino-sidebar-collapsed"
        ) === "1"
        && window.innerWidth > 1040
    ) {
        body.classList.add(
            "sidebar-collapsed"
        );
    }

    sidebarCollapse
        ?.addEventListener(
            "click",
            () => {
                body.classList.toggle(
                    "sidebar-collapsed"
                );

                storage.set(
                    "buildino-sidebar-collapsed",
                    body.classList
                        .contains(
                            "sidebar-collapsed"
                        )
                        ? "1"
                        : "0"
                );
            }
        );

    const navGroups = [
        ...document.querySelectorAll(
            "[data-nav-group]"
        ),
    ];

    navGroups.forEach(
        (group) => {
            const name =
                group.dataset
                    .navGroup;

            const saved =
                storage.get(
                    `buildino-nav-${name}`
                );

            if (
                saved === "1"
                && ! group.classList
                    .contains(
                        "is-open"
                    )
            ) {
                group.classList.add(
                    "is-open"
                );
            }

            group
                .querySelector(
                    "[data-nav-toggle]"
                )
                ?.addEventListener(
                    "click",
                    () => {
                        group.classList.toggle(
                            "is-open"
                        );

                        storage.set(
                            `buildino-nav-${name}`,
                            group.classList
                                .contains(
                                    "is-open"
                                )
                                ? "1"
                                : "0"
                        );
                    }
                );
        }
    );

    document
        .querySelectorAll(
            ".sidebar .nav-link"
        )
        .forEach(
            (link) => {
                link.addEventListener(
                    "click",
                    closeMobileSidebar
                );
            }
        );

    /* ---------------------------------------------------------------
       Popovers
    ---------------------------------------------------------------- */

    const popovers = [
        ...document.querySelectorAll(
            "[data-popover]"
        ),
    ];

    const closePopovers = (
        except = null
    ) => {
        popovers.forEach(
            (popover) => {
                if (
                    popover
                    !== except
                ) {
                    popover.classList
                        .remove(
                            "is-open"
                        );
                }
            }
        );
    };

    popovers.forEach(
        (popover) => {
            popover
                .querySelector(
                    "[data-popover-trigger]"
                )
                ?.addEventListener(
                    "click",
                    (event) => {
                        event.stopPropagation();

                        const next =
                            ! popover.classList
                                .contains(
                                    "is-open"
                                );

                        closePopovers(
                            popover
                        );

                        popover.classList
                            .toggle(
                                "is-open",
                                next
                            );
                    }
                );

            popover
                .querySelector(
                    "[data-popover-menu]"
                )
                ?.addEventListener(
                    "click",
                    (event) =>
                        event
                            .stopPropagation()
                );
        }
    );

    document.addEventListener(
        "click",
        () => closePopovers()
    );

    /* ---------------------------------------------------------------
       Bootstrap helpers + Persian presentation
    ---------------------------------------------------------------- */

    if (
        window.bootstrap
        && typeof window.bootstrap.Tooltip
            === "function"
    ) {
        document
            .querySelectorAll(
                '[data-bs-toggle="tooltip"]'
            )
            .forEach(
                (element) => {
                    new window.bootstrap.Tooltip(
                        element,
                        {
                            container:
                                "body",
                        }
                    );
                }
            );
    }

    document
        .querySelectorAll(
            "[data-money-value]"
        )
        .forEach(
            (element) => {
                element.textContent =
                    window.BuildinoUI
                        .number(
                            element.dataset
                                .moneyValue
                        );
            }
        );

    document
        .querySelectorAll(
            "[data-jdate]"
        )
        .forEach(
            (element) => {
                element.textContent =
                    window.BuildinoUI
                        .dateTime(
                            element.dataset
                                .jdate
                        );
            }
        );

    /* ---------------------------------------------------------------
       Header notifications
    ---------------------------------------------------------------- */

    const notificationBadge =
        document.getElementById(
            "managementNotificationBadge"
        );

    const notificationUnreadText =
        document.getElementById(
            "managementNotificationUnreadText"
        );

    const notificationReadAll =
        document.getElementById(
            "managementNotificationsReadAll"
        );

    const notificationItems = [
        ...document.querySelectorAll(
            "[data-management-notification]"
        ),
    ];

    const csrfToken =
        document
            .querySelector(
                'meta[name="csrf-token"]'
            )
            ?.getAttribute(
                "content"
            )
        || "";

    const notificationFetch =
        async (
            url
        ) => {
            const response =
                await fetch(
                    url,
                    {
                        method: "POST",
                        credentials:
                            "same-origin",
                        headers: {
                            Accept:
                                "application/json",
                            "X-Requested-With":
                                "XMLHttpRequest",
                            "X-CSRF-TOKEN":
                                csrfToken,
                        },
                    }
                );

            if (! response.ok) {
                throw new Error(
                    "بروزرسانی وضعیت اعلان انجام نشد."
                );
            }

            return response.json();
        };

    const updateUnreadBadge =
        (count) => {
            const safeCount =
                Math.max(
                    0,
                    Number(count) || 0
                );

            if (
                notificationBadge
            ) {
                notificationBadge.hidden =
                    safeCount < 1;

                notificationBadge.textContent =
                    safeCount > 99
                        ? "99+"
                        : toFaDigits(
                            safeCount
                        );
            }

            if (
                notificationUnreadText
            ) {
                notificationUnreadText.textContent =
                    window.BuildinoUI
                        .number(
                            safeCount
                        );
            }

            if (
                notificationReadAll
            ) {
                notificationReadAll.disabled =
                    safeCount < 1;
            }
        };

    const currentUnreadCount =
        () =>
            notificationItems
                .filter(
                    (item) =>
                        item.classList
                            .contains(
                                "is-unread"
                            )
                )
                .length;

    notificationItems.forEach(
        (item) => {
            item.addEventListener(
                "click",
                async () => {
                    const id =
                        item.dataset
                            .notificationId;

                    const href =
                        item.dataset
                            .notificationHref;

                    try {
                        if (
                            item.classList
                                .contains(
                                    "is-unread"
                                )
                        ) {
                            await notificationFetch(
                                `/api/v1/notifications/${encodeURIComponent(id)}/read`
                            );

                            item.classList
                                .remove(
                                    "is-unread"
                                );

                            updateUnreadBadge(
                                currentUnreadCount()
                            );
                        }

                        if (href) {
                            window.location.href =
                                href;
                        }
                    } catch (error) {
                        window.BuildinoUI
                            .toast(
                                error.message,
                                "danger"
                            );
                    }
                }
            );
        }
    );

    notificationReadAll
        ?.addEventListener(
            "click",
            async (
                event
            ) => {
                event.stopPropagation();

                try {
                    await notificationFetch(
                        "/api/v1/notifications/read-all"
                    );

                    notificationItems
                        .forEach(
                            (item) =>
                                item.classList
                                    .remove(
                                        "is-unread"
                                    )
                        );

                    updateUnreadBadge(
                        0
                    );

                    window.BuildinoUI
                        .toast(
                            "همه اعلان‌ها خوانده شدند.",
                            "success"
                        );
                } catch (error) {
                    window.BuildinoUI
                        .toast(
                            error.message,
                            "danger"
                        );
                }
            }
        );

    /* ---------------------------------------------------------------
       Command palette
    ---------------------------------------------------------------- */

    const commandData =
        window.BuildinoManagementUi
        || {};

    const commandResources =
        Array.isArray(
            commandData.resources
        )
            ? commandData.resources
            : [];

    const commandTrigger =
        document.getElementById(
            "commandTrigger"
        );

    const commandBackdrop =
        document.getElementById(
            "commandBackdrop"
        );

    const commandPalette =
        document.getElementById(
            "commandPalette"
        );

    const commandSearch =
        document.getElementById(
            "commandSearch"
        );

    const commandResults =
        document.getElementById(
            "commandResults"
        );

    const commandClose =
        document.getElementById(
            "commandClose"
        );

    let commandFiltered =
        [...commandResources];

    let commandIndex = 0;

    const normalize =
        (value) =>
            String(
                value ?? ""
            )
                .trim()
                .toLocaleLowerCase(
                    "fa"
                )
                .replace(
                    /ي/g,
                    "ی"
                )
                .replace(
                    /ك/g,
                    "ک"
                );

    const openCommand =
        () => {
            if (! commandPalette) {
                return;
            }

            closePopovers();

            body.classList.add(
                "command-open"
            );

            commandPalette
                .setAttribute(
                    "aria-hidden",
                    "false"
                );

            commandSearch.value =
                "";

            commandFiltered =
                [...commandResources];

            commandIndex = 0;

            renderCommands();

            window.setTimeout(
                () =>
                    commandSearch
                        ?.focus(),
                30
            );
        };

    const closeCommand =
        () => {
            body.classList.remove(
                "command-open"
            );

            commandPalette
                ?.setAttribute(
                    "aria-hidden",
                    "true"
                );
        };

    const commandIcon =
        (group) => {
            const value =
                normalize(group);

            if (
                value.includes(
                    "ساختمان"
                )
            ) {
                return "⌂";
            }

            if (
                value.includes(
                    "مالی"
                )
            ) {
                return "﷼";
            }

            if (
                value.includes(
                    "کاربر"
                )
            ) {
                return "◎";
            }

            if (
                value.includes(
                    "پشتیبانی"
                )
            ) {
                return "◌";
            }

            return "◆";
        };

    const renderCommands =
        () => {
            if (! commandResults) {
                return;
            }

            if (
                ! commandFiltered
                    .length
            ) {
                commandResults.innerHTML =
                    `
                    <div class="command-empty">
                        نتیجه‌ای برای این عبارت پیدا نشد.
                    </div>
                `;

                return;
            }

            commandResults.innerHTML =
                commandFiltered
                    .map(
                        (
                            item,
                            index
                        ) => `
                            <button
                                type="button"
                                class="command-item ${
                                    index
                                    === commandIndex
                                        ? "is-selected"
                                        : ""
                                }"
                                data-command-index="${index}"
                            >
                                <span class="command-item__icon">
                                    ${commandIcon(item.group)}
                                </span>

                                <span>
                                    <strong>${escapeHtml(item.title)}</strong>
                                    <span>${escapeHtml(item.description)}</span>
                                </span>

                                <small>${escapeHtml(item.group)}</small>
                            </button>
                        `
                    )
                    .join("");

            commandResults
                .querySelectorAll(
                    "[data-command-index]"
                )
                .forEach(
                    (button) => {
                        button
                            .addEventListener(
                                "mouseenter",
                                () => {
                                    commandIndex =
                                        Number(
                                            button
                                                .dataset
                                                .commandIndex
                                        );

                                    highlightCommand();
                                }
                            );

                        button
                            .addEventListener(
                                "click",
                                () => {
                                    const item =
                                        commandFiltered[
                                            Number(
                                                button
                                                    .dataset
                                                    .commandIndex
                                            )
                                        ];

                                    if (
                                        item?.url
                                    ) {
                                        window.location.href =
                                            item.url;
                                    }
                                }
                            );
                    }
                );
        };

    const highlightCommand =
        () => {
            commandResults
                ?.querySelectorAll(
                    "[data-command-index]"
                )
                .forEach(
                    (button) => {
                        button.classList
                            .toggle(
                                "is-selected",
                                Number(
                                    button
                                        .dataset
                                        .commandIndex
                                )
                                === commandIndex
                            );
                    }
                );

            commandResults
                ?.querySelector(
                    `[data-command-index="${commandIndex}"]`
                )
                ?.scrollIntoView({
                    block: "nearest",
                });
        };

    const escapeHtml =
        (value) => {
            const element =
                document.createElement(
                    "span"
                );

            element.textContent =
                value ?? "";

            return element.innerHTML;
        };

    commandTrigger
        ?.addEventListener(
            "click",
            openCommand
        );

    commandBackdrop
        ?.addEventListener(
            "click",
            closeCommand
        );

    commandClose
        ?.addEventListener(
            "click",
            closeCommand
        );

    commandSearch
        ?.addEventListener(
            "input",
            () => {
                const term =
                    normalize(
                        commandSearch
                            .value
                    );

                commandFiltered =
                    ! term
                        ? [
                            ...commandResources,
                        ]
                        : commandResources
                            .filter(
                                (item) =>
                                    normalize(
                                        [
                                            item.title,
                                            item.description,
                                            item.group,
                                        ].join(
                                            " "
                                        )
                                    ).includes(
                                        term
                                    )
                            );

                commandIndex = 0;
                renderCommands();
            }
        );

    document.addEventListener(
        "keydown",
        (event) => {
            const tag =
                document.activeElement
                    ?.tagName
                    ?.toLowerCase();

            const typing =
                ["input", "textarea", "select"]
                    .includes(tag);

            if (
                (
                    event.ctrlKey
                    || event.metaKey
                )
                && event.key
                    .toLowerCase()
                    === "k"
            ) {
                event.preventDefault();

                body.classList
                    .contains(
                        "command-open"
                    )
                    ? closeCommand()
                    : openCommand();

                return;
            }

            if (
                event.key === "/"
                && ! typing
                && ! body.classList
                    .contains(
                        "command-open"
                    )
            ) {
                event.preventDefault();
                openCommand();
                return;
            }

            if (
                ! body.classList
                    .contains(
                        "command-open"
                    )
            ) {
                if (
                    event.key
                    === "Escape"
                ) {
                    closePopovers();
                    closeMobileSidebar();
                }

                return;
            }

            if (
                event.key
                === "Escape"
            ) {
                event.preventDefault();
                closeCommand();
                return;
            }

            if (
                event.key
                === "ArrowDown"
            ) {
                event.preventDefault();

                commandIndex =
                    Math.min(
                        commandIndex + 1,
                        commandFiltered
                            .length - 1
                    );

                highlightCommand();
                return;
            }

            if (
                event.key
                === "ArrowUp"
            ) {
                event.preventDefault();

                commandIndex =
                    Math.max(
                        commandIndex - 1,
                        0
                    );

                highlightCommand();
                return;
            }

            if (
                event.key
                === "Enter"
                && commandFiltered[
                    commandIndex
                ]?.url
            ) {
                event.preventDefault();

                window.location.href =
                    commandFiltered[
                        commandIndex
                    ].url;
            }
        }
    );

    /* ---------------------------------------------------------------
       Dashboard progress bars
    ---------------------------------------------------------------- */

    const bars =
        document.querySelectorAll(
            ".progress-bar[data-value]"
        );

    requestAnimationFrame(
        () => {
            bars.forEach(
                (bar) => {
                    const value =
                        Number(
                            bar
                                .dataset
                                .value
                            || 0
                        );

                    const max =
                        Math.max(
                            Number(
                                bar
                                    .dataset
                                    .max
                                || 1
                            ),
                            1
                        );

                    const percent =
                        Math.max(
                            0,
                            Math.min(
                                100,
                                (
                                    value
                                    / max
                                )
                                * 100
                            )
                        );

                    bar.style.width =
                        `${percent}%`;
                }
            );
        }
    );

    /* ---------------------------------------------------------------
       Dashboard section navigation
    ---------------------------------------------------------------- */

    const sections = [
        ...document.querySelectorAll(
            "section[id]"
        ),
    ];

    const hashLinks = [
        ...document.querySelectorAll(
            ".sidebar a[href*='#']"
        ),
    ];

    if (
        "IntersectionObserver"
        in window
        && sections.length
        && hashLinks.length
    ) {
        const observer =
            new IntersectionObserver(
                (entries) => {
                    const visible =
                        entries
                            .filter(
                                (entry) =>
                                    entry
                                        .isIntersecting
                            )
                            .sort(
                                (a, b) =>
                                    b
                                        .intersectionRatio
                                    - a
                                        .intersectionRatio
                            )[0];

                    if (! visible) {
                        return;
                    }

                    const id =
                        visible
                            .target
                            .id;

                    hashLinks
                        .forEach(
                            (link) => {
                                const href =
                                    link.getAttribute(
                                        "href"
                                    )
                                    || "";

                                if (
                                    href.endsWith(
                                        `#${id}`
                                    )
                                ) {
                                    link.classList
                                        .add(
                                            "is-active"
                                        );
                                }
                            }
                        );
                },
                {
                    rootMargin:
                        "-15% 0px -72% 0px",
                    threshold:
                        [0.08, 0.3],
                }
            );

        sections.forEach(
            (section) =>
                observer.observe(
                    section
                )
        );
    }

    window.addEventListener(
        "resize",
        () => {
            if (
                window.innerWidth
                > 1040
            ) {
                closeMobileSidebar();
            }
        }
    );
})();
