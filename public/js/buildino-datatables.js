
(() => {
    "use strict";

    const decodeColumns =
        (encoded) => {
            if (! encoded) {
                return [];
            }

            try {
                const json =
                    decodeURIComponent(
                        Array.prototype
                            .map
                            .call(
                                atob(
                                    encoded
                                ),
                                (char) =>
                                    "%"
                                    + (
                                        "00"
                                        + char
                                            .charCodeAt(
                                                0
                                            )
                                            .toString(
                                                16
                                            )
                                    )
                                        .slice(
                                            -2
                                        )
                            )
                            .join(
                                ""
                            )
                    );

                return JSON.parse(
                    json
                );
            } catch (
                error
            ) {
                console.error(
                    "Buildino DataTable columns decode failed.",
                    error
                );

                return [];
            }
        };

    const statusRenderer =
        (
            data,
            type,
            row
        ) => {
            if (
                type !== "display"
            ) {
                return data;
            }

            const tone =
                row.status_tone
                || "info";

            const span =
                document.createElement(
                    "span"
                );

            span.className =
                `buildino-dt-status buildino-dt-status--${tone}`;

            span.textContent =
                data
                || "—";

            return span.outerHTML;
        };

    const actionRenderer =
        (
            data,
            type
        ) => {
            if (
                type !== "display"
            ) {
                return data;
            }

            if (! data) {
                return "—";
            }

            const link =
                document.createElement(
                    "a"
                );

            link.href =
                data;

            link.className =
                "buildino-dt-action";

            link.textContent =
                "مشاهده";

            return link.outerHTML;
        };

    const columnDefinition =
        (column) => {
            const definition = {
                data:
                    column.data,
                name:
                    column.name
                    || column.data,
                orderable:
                    column.orderable
                    !== false,
                searchable:
                    column.searchable
                    !== false,
                defaultContent:
                    "—",
            };

            if (
                column.status
            ) {
                definition.render =
                    statusRenderer;
            }

            if (
                column.action
            ) {
                definition.render =
                    actionRenderer;

                definition.className =
                    "text-nowrap";
            }

            return definition;
        };

    const persianLanguage = {
        processing:
            "در حال پردازش...",
        search:
            "جستجو:",
        lengthMenu:
            "نمایش _MENU_ رکورد",
        info:
            "نمایش _START_ تا _END_ از _TOTAL_ رکورد",
        infoEmpty:
            "رکوردی وجود ندارد",
        infoFiltered:
            "(فیلتر از _MAX_ رکورد)",
        zeroRecords:
            "رکوردی مطابق جستجو پیدا نشد",
        emptyTable:
            "داده‌ای برای نمایش وجود ندارد",
        loadingRecords:
            "در حال دریافت...",
        paginate: {
            first:
                "اول",
            previous:
                "قبلی",
            next:
                "بعدی",
            last:
                "آخر",
        },
    };

    const filtersFor =
        (table) => {
            const scope =
                table.closest(
                    "[data-dt-filter-scope]"
                );

            if (! scope) {
                return {};
            }

            const filters = {};

            scope
                .querySelectorAll(
                    "[data-dt-filter]"
                )
                .forEach(
                    (field) => {
                        const key =
                            field.dataset
                                .dtFilter;

                        if (
                            key
                            && field.value
                        ) {
                            filters[key] =
                                field.value;
                        }
                    }
                );

            return filters;
        };

    const init =
        (table) => {
            if (
                ! window.DataTable
                || table.dataset
                    .dtReady
                === "1"
            ) {
                return;
            }

            const columns =
                decodeColumns(
                    table.dataset
                        .dtColumns
                );

            if (! columns.length) {
                return;
            }

            const shell =
                table.closest(
                    "[data-dt-shell]"
                );

            const loading =
                shell
                    ?.querySelector(
                        "[data-dt-loading]"
                    );

            const countTarget =
                table.dataset
                    .dtCountTarget
                    ? document.querySelector(
                        table.dataset
                            .dtCountTarget
                    )
                    : null;

            shell?.setAttribute(
                "aria-busy",
                "true"
            );

            const instance =
                new window.DataTable(
                    table,
                    {
                        processing:
                            true,
                        serverSide:
                            true,
                        responsive:
                            true,
                        searchDelay:
                            350,
                        pageLength:
                            Number(
                                table.dataset
                                    .dtPageLength
                                || 10
                            ),
                        lengthMenu: [
                            [
                                10,
                                15,
                                25,
                                50,
                                100,
                            ],
                            [
                                10,
                                15,
                                25,
                                50,
                                100,
                            ],
                        ],
                        order: [],
                        ajax: {
                            url:
                                table.dataset
                                    .dtUrl,
                            type:
                                "GET",
                            data:
                                (payload) => {
                                    Object.assign(
                                        payload,
                                        filtersFor(
                                            table
                                        )
                                    );
                                },
                            error:
                                (
                                    xhr
                                ) => {
                                    const message =
                                        xhr
                                            ?.responseJSON
                                            ?.message
                                        || "دریافت اطلاعات جدول ناموفق بود.";

                                    loading
                                        ?.setAttribute(
                                            "hidden",
                                            "hidden"
                                        );

                                    shell?.setAttribute(
                                        "aria-busy",
                                        "false"
                                    );

                                    if (
                                        window.BuildinoUI
                                        && typeof window.BuildinoUI.toast
                                            === "function"
                                    ) {
                                        window.BuildinoUI.toast(
                                            `خطای جدول: ${message}`,
                                            "danger"
                                        );
                                    }
                                },
                        },
                        columns:
                            columns.map(
                                columnDefinition
                            ),
                        language:
                            persianLanguage,
                        drawCallback:
                            function () {
                                loading
                                    ?.setAttribute(
                                        "hidden",
                                        "hidden"
                                    );

                                shell?.setAttribute(
                                    "aria-busy",
                                    "false"
                                );

                                if (
                                    countTarget
                                ) {
                                    const info =
                                        this.api()
                                            .page
                                            .info();

                                    countTarget.textContent =
                                        `${Number(info.recordsDisplay).toLocaleString("fa-IR")} رکورد`;
                                }
                            },
                    }
                );

            table.dataset
                .dtReady =
                "1";

            const scope =
                table.closest(
                    "[data-dt-filter-scope]"
                );

            scope
                ?.querySelectorAll(
                    "[data-dt-filter]"
                )
                .forEach(
                    (field) => {
                        field.addEventListener(
                            "change",
                            () => {
                                loading?.removeAttribute(
                                    "hidden"
                                );
                                shell?.setAttribute(
                                    "aria-busy",
                                    "true"
                                );
                                instance
                                    .ajax
                                    .reload();
                            }
                        );
                    }
                );

            scope
                ?.querySelector(
                    "[data-dt-reset]"
                )
                ?.addEventListener(
                    "click",
                    () => {
                        scope
                            .querySelectorAll(
                                "[data-dt-filter]"
                            )
                            .forEach(
                                (field) => {
                                    field.value =
                                        "";
                                }
                            );

                        loading?.removeAttribute(
                            "hidden"
                        );
                        shell?.setAttribute(
                            "aria-busy",
                            "true"
                        );

                        instance
                            .search(
                                ""
                            )
                            .ajax
                            .reload();
                    }
                );
        };

    const boot =
        () => {
            document
                .querySelectorAll(
                    ".js-server-datatable"
                )
                .forEach(
                    init
                );
        };

    if (
        document.readyState
        === "loading"
    ) {
        document.addEventListener(
            "DOMContentLoaded",
            boot
        );
    } else {
        boot();
    }

    window.BuildinoDataTables = {
        boot,
    };
})();
