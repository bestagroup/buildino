(() => {
    "use strict";

    const root = document.documentElement;
    const body = document.body;

    const savedTheme = localStorage.getItem("buildino-theme");

    if (savedTheme === "dark" || savedTheme === "light") {
        root.dataset.theme = savedTheme;
    }

    const themeToggle = document.getElementById("themeToggle");

    themeToggle?.addEventListener("click", () => {
        const next =
            root.dataset.theme === "dark"
                ? "light"
                : "dark";

        root.dataset.theme = next;
        localStorage.setItem(
            "buildino-theme",
            next
        );
    });

    const sidebarToggle =
        document.getElementById(
            "sidebarToggle"
        );

    const sidebarBackdrop =
        document.getElementById(
            "sidebarBackdrop"
        );

    const closeSidebar = () => {
        body.classList.remove(
            "sidebar-open"
        );
    };

    sidebarToggle?.addEventListener(
        "click",
        () => {
            body.classList.toggle(
                "sidebar-open"
            );
        }
    );

    sidebarBackdrop?.addEventListener(
        "click",
        closeSidebar
    );

    document
        .querySelectorAll(
            ".sidebar .nav-link"
        )
        .forEach((link) => {
            link.addEventListener(
                "click",
                closeSidebar
            );
        });

    const bars = document.querySelectorAll(
        ".progress-bar[data-value]"
    );

    requestAnimationFrame(() => {
        bars.forEach((bar) => {
            const value = Number(
                bar.dataset.value || 0
            );
            const max = Math.max(
                Number(
                    bar.dataset.max || 1
                ),
                1
            );

            const percent = Math.max(
                0,
                Math.min(
                    100,
                    (value / max) * 100
                )
            );

            bar.style.width =
                `${percent}%`;
        });
    });

    const sections = [
        ...document.querySelectorAll(
            "section[id]"
        ),
    ];

    const links = [
        ...document.querySelectorAll(
            ".sidebar .nav-link[href^='#']"
        ),
    ];

    if (
        "IntersectionObserver" in window
        && sections.length
        && links.length
    ) {
        const observer =
            new IntersectionObserver(
                (entries) => {
                    const visible =
                        entries
                            .filter(
                                (entry) =>
                                    entry.isIntersecting
                            )
                            .sort(
                                (a, b) =>
                                    b.intersectionRatio
                                    - a.intersectionRatio
                            )[0];

                    if (! visible) {
                        return;
                    }

                    const id =
                        visible.target.id;

                    links.forEach(
                        (link) => {
                            link.classList.toggle(
                                "is-active",
                                link.getAttribute(
                                    "href"
                                )
                                    === `#${id}`
                            );
                        }
                    );
                },
                {
                    rootMargin:
                        "-20% 0px -65% 0px",
                    threshold:
                        [0.05, 0.2, 0.5],
                }
            );

        sections.forEach(
            (section) =>
                observer.observe(section)
        );
    }

    window.addEventListener(
        "resize",
        () => {
            if (
                window.innerWidth > 980
            ) {
                closeSidebar();
            }
        }
    );
})();
