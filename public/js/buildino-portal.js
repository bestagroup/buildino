(() => {
    "use strict";

    const portal =
        window.BuildinoPortal
        || {};

    const csrf =
        portal.csrfToken
        || document
            .querySelector(
                'meta[name="csrf-token"]'
            )
            ?.getAttribute(
                "content"
            );

    const $ =
        (
            selector,
            root = document
        ) =>
            root.querySelector(
                selector
            );

    const $$ =
        (
            selector,
            root = document
        ) =>
            [
                ...root.querySelectorAll(
                    selector
                ),
            ];

    const normalizeValue =
        (value) =>
            value === ""
                ? null
                : value;

    const api =
        async (
            url,
            options = {}
        ) => {
            const response =
                await fetch(
                    url,
                    {
                        credentials:
                            "same-origin",
                        ...options,
                        headers: {
                            Accept:
                                "application/json",
                            "Content-Type":
                                "application/json",
                            "X-Requested-With":
                                "XMLHttpRequest",
                            "X-CSRF-TOKEN":
                                csrf,
                            ...(
                                options.headers
                                || {}
                            ),
                        },
                    }
                );

            const contentType =
                response.headers.get(
                    "content-type"
                )
                || "";

            const payload =
                response.status === 204
                    ? null
                    : (
                        contentType.includes(
                            "application/json"
                        )
                            ? await response.json()
                            : await response.text()
                    );

            if (! response.ok) {
                let message =
                    `خطای HTTP ${response.status}`;

                if (
                    payload
                    && typeof payload
                    === "object"
                ) {
                    if (payload.message) {
                        message =
                            payload.message;
                    } else if (
                        payload.errors
                    ) {
                        message =
                            Object.values(
                                payload.errors
                            )
                                .flat()
                                .join(
                                    " | "
                                );
                    }
                } else if (
                    typeof payload
                    === "string"
                    && payload
                ) {
                    message =
                        payload;
                }

                const error =
                    new Error(
                        message
                    );

                error.status =
                    response.status;
                error.payload =
                    payload;

                throw error;
            }

            return payload;
        };

    const toast =
        (
            message,
            tone = "success"
        ) => {
            if (
                window.Swal
            ) {
                window.Swal.fire({
                    toast: true,
                    position:
                        "top-start",
                    icon:
                        tone,
                    title:
                        message,
                    showConfirmButton:
                        false,
                    timer:
                        3500,
                    timerProgressBar:
                        true,
                });

                return;
            }

            window.alert(
                message
            );
        };

    const confirmAction =
        async (
            title,
            text
        ) => {
            if (
                window.Swal
            ) {
                const result =
                    await window.Swal.fire({
                        title,
                        text,
                        icon:
                            "question",
                        showCancelButton:
                            true,
                        confirmButtonText:
                            "بله، ادامه بده",
                        cancelButtonText:
                            "انصراف",
                        reverseButtons:
                            true,
                    });

                return result.isConfirmed;
            }

            return window.confirm(
                `${title}\n${text}`
            );
        };

    const submitting =
        (
            button,
            state
        ) => {
            if (! button) {
                return;
            }

            if (state) {
                button.dataset
                    .originalText =
                    button.textContent;

                button.disabled =
                    true;

                button.textContent =
                    "در حال انجام...";
            } else {
                button.disabled =
                    false;

                if (
                    button.dataset
                        .originalText
                ) {
                    button.textContent =
                        button.dataset
                            .originalText;
                }
            }
        };

    const closeModal =
        (form) => {
            const modalElement =
                form.closest(
                    ".modal"
                );

            if (
                modalElement
                && window.bootstrap
            ) {
                window.bootstrap
                    .Modal
                    .getOrCreateInstance(
                        modalElement
                    )
                    .hide();
            }
        };

    const successReload =
        (
            form,
            message
        ) => {
            closeModal(
                form
            );

            toast(
                message
            );

            window.setTimeout(
                () =>
                    window.location
                        .reload(),
                650
            );
        };

    /* ---------------------------------------------------------------
       Theme / Sidebar
    ---------------------------------------------------------------- */

    const root =
        document.documentElement;

    const savedTheme =
        (() => {
            try {
                return localStorage
                    .getItem(
                        "buildino-portal-theme"
                    );
            } catch {
                return null;
            }
        })();

    if (
        savedTheme === "dark"
        || savedTheme === "light"
    ) {
        root.dataset.theme =
            savedTheme;
    }

    $("#portalThemeToggle")
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

                try {
                    localStorage
                        .setItem(
                            "buildino-portal-theme",
                            next
                        );
                } catch {
                    // Optional persistence.
                }
            }
        );

    $("#portalSidebarToggle")
        ?.addEventListener(
            "click",
            () =>
                document.body
                    .classList
                    .toggle(
                        "portal-sidebar-open"
                    )
        );

    $("#portalSidebarBackdrop")
        ?.addEventListener(
            "click",
            () =>
                document.body
                    .classList
                    .remove(
                        "portal-sidebar-open"
                    )
        );

    /* ---------------------------------------------------------------
       Jalali rendering
    ---------------------------------------------------------------- */

    const toPersianDigits =
        (value) =>
            String(
                value
                ?? ""
            ).replace(
                /\d/g,
                (digit) =>
                    "۰۱۲۳۴۵۶۷۸۹"[
                        Number(
                            digit
                        )
                    ]
            );

    $$("[data-jdate]")
        .forEach(
            (element) => {
                const raw =
                    element.dataset
                        .jdate;

                if (! raw) {
                    return;
                }

                try {
                    if (
                        window.JDate
                    ) {
                        const date =
                            new window.JDate(
                                new Date(
                                    raw
                                )
                            );

                        element.textContent =
                            toPersianDigits(
                                date
                                    .format(
                                        "YYYY/MM/DD"
                                    )
                            );
                    }
                } catch {
                    // Keep server fallback.
                }
            }
        );

    /* ---------------------------------------------------------------
       Notifications
    ---------------------------------------------------------------- */

    $$(
        "[data-notification-id]"
    ).forEach(
        (button) => {
            button.addEventListener(
                "click",
                async () => {
                    const id =
                        button.dataset
                            .notificationId;

                    try {
                        await api(
                            `/api/v1/notifications/${encodeURIComponent(id)}/read`,
                            {
                                method:
                                    "POST",
                                body:
                                    JSON.stringify(
                                        {}
                                    ),
                            }
                        );

                        button.classList
                            .remove(
                                "is-unread"
                            );

                        const href =
                            button.dataset
                                .notificationHref;

                        if (href) {
                            window.location.href =
                                href;
                        }
                    } catch (
                        error
                    ) {
                        toast(
                            error.message,
                            "error"
                        );
                    }
                }
            );
        }
    );

    $("[data-portal-read-all]")
        ?.addEventListener(
            "click",
            async () => {
                try {
                    await api(
                        "/api/v1/notifications/read-all",
                        {
                            method:
                                "POST",
                            body:
                                JSON.stringify(
                                    {}
                                ),
                        }
                    );

                    $$(
                        "[data-notification-id]"
                    ).forEach(
                        (item) =>
                            item.classList
                                .remove(
                                    "is-unread"
                                )
                    );

                    toast(
                        "همه اعلان‌ها خوانده شدند."
                    );
                } catch (
                    error
                ) {
                    toast(
                        error.message,
                        "error"
                    );
                }
            }
        );

    /* ---------------------------------------------------------------
       Resident forms
    ---------------------------------------------------------------- */

    const buildingFromUnit =
        (select) =>
            select
                ?.selectedOptions[
                    0
                ]
                ?.dataset
                ?.buildingId
            || null;

    const value =
        (
            form,
            name
        ) =>
            normalizeValue(
                form.elements[
                    name
                ]?.value
                ?? ""
            );

    $(
        '[data-portal-form="guest"]'
    )?.addEventListener(
        "submit",
        async (
            event
        ) => {
            event.preventDefault();

            const form =
                event.currentTarget;

            const submit =
                form.querySelector(
                    '[type="submit"]'
                );

            submitting(
                submit,
                true
            );

            try {
                const unitId =
                    value(
                        form,
                        "unit_id"
                    );

                await api(
                    `/api/v1/units/${encodeURIComponent(unitId)}/guest-visits`,
                    {
                        method:
                            "POST",
                        body:
                            JSON.stringify({
                                guest: {
                                    first_name:
                                        value(
                                            form,
                                            "first_name"
                                        ),
                                    last_name:
                                        value(
                                            form,
                                            "last_name"
                                        ),
                                    mobile:
                                        value(
                                            form,
                                            "mobile"
                                        ),
                                    vehicle_plate:
                                        value(
                                            form,
                                            "vehicle_plate"
                                        ),
                                },
                                expected_entry_at:
                                    value(
                                        form,
                                        "expected_entry_at"
                                    ),
                                expected_exit_at:
                                    value(
                                        form,
                                        "expected_exit_at"
                                    ),
                                description:
                                    value(
                                        form,
                                        "description"
                                    ),
                            }),
                    }
                );

                successReload(
                    form,
                    "مهمان با موفقیت ثبت شد."
                );
            } catch (
                error
            ) {
                toast(
                    error.message,
                    "error"
                );
            } finally {
                submitting(
                    submit,
                    false
                );
            }
        }
    );

    $(
        '[data-portal-form="service"]'
    )?.addEventListener(
        "submit",
        async (
            event
        ) => {
            event.preventDefault();

            const form =
                event.currentTarget;

            const submit =
                form.querySelector(
                    '[type="submit"]'
                );

            const unitSelect =
                form.elements
                    .unit_id;

            submitting(
                submit,
                true
            );

            try {
                await api(
                    "/api/v1/service-requests",
                    {
                        method:
                            "POST",
                        body:
                            JSON.stringify({
                                building_id:
                                    Number(
                                        buildingFromUnit(
                                            unitSelect
                                        )
                                    ),
                                unit_id:
                                    Number(
                                        unitSelect
                                            .value
                                    ),
                                type:
                                    value(
                                        form,
                                        "type"
                                    ),
                                priority:
                                    value(
                                        form,
                                        "priority"
                                    ),
                                title:
                                    value(
                                        form,
                                        "title"
                                    ),
                                description:
                                    value(
                                        form,
                                        "description"
                                    ),
                            }),
                    }
                );

                successReload(
                    form,
                    "درخواست خدمت ثبت شد."
                );
            } catch (
                error
            ) {
                toast(
                    error.message,
                    "error"
                );
            } finally {
                submitting(
                    submit,
                    false
                );
            }
        }
    );

    $(
        '[data-portal-form="support"]'
    )?.addEventListener(
        "submit",
        async (
            event
        ) => {
            event.preventDefault();

            const form =
                event.currentTarget;

            const submit =
                form.querySelector(
                    '[type="submit"]'
                );

            const unitSelect =
                form.elements
                    .unit_id;

            submitting(
                submit,
                true
            );

            try {
                await api(
                    "/api/v1/support-tickets",
                    {
                        method:
                            "POST",
                        body:
                            JSON.stringify({
                                building_id:
                                    Number(
                                        buildingFromUnit(
                                            unitSelect
                                        )
                                    ),
                                unit_id:
                                    Number(
                                        unitSelect
                                            .value
                                    ),
                                support_category_id:
                                    value(
                                        form,
                                        "support_category_id"
                                    )
                                        ? Number(
                                            value(
                                                form,
                                                "support_category_id"
                                            )
                                        )
                                        : null,
                                subject:
                                    value(
                                        form,
                                        "subject"
                                    ),
                                description:
                                    value(
                                        form,
                                        "description"
                                    ),
                                priority:
                                    value(
                                        form,
                                        "priority"
                                    ),
                            }),
                    }
                );

                successReload(
                    form,
                    "تیکت پشتیبانی ثبت شد."
                );
            } catch (
                error
            ) {
                toast(
                    error.message,
                    "error"
                );
            } finally {
                submitting(
                    submit,
                    false
                );
            }
        }
    );

    const reservationForm =
        $(
            '[data-portal-form="reservation"]'
        );

    const syncReservationUnits =
        () => {
            if (
                ! reservationForm
            ) {
                return;
            }

            const facility =
                reservationForm
                    .elements
                    .facility_id;

            const unit =
                reservationForm
                    .elements
                    .unit_id;

            const buildingId =
                facility
                    ?.selectedOptions[
                        0
                    ]
                    ?.dataset
                    ?.buildingId;

            if (! buildingId) {
                return;
            }

            [
                ...unit.options,
            ].forEach(
                (option) => {
                    option.hidden =
                        option.dataset
                            .buildingId
                        !== buildingId;
                }
            );

            const firstVisible =
                [
                    ...unit.options,
                ].find(
                    (option) =>
                        ! option.hidden
                );

            if (
                unit.selectedOptions[
                    0
                ]?.hidden
                && firstVisible
            ) {
                unit.value =
                    firstVisible
                        .value;
            }
        };

    reservationForm
        ?.elements
        ?.facility_id
        ?.addEventListener(
            "change",
            syncReservationUnits
        );

    syncReservationUnits();

    reservationForm
        ?.addEventListener(
            "submit",
            async (
                event
            ) => {
                event.preventDefault();

                const form =
                    event.currentTarget;

                const submit =
                    form.querySelector(
                        '[type="submit"]'
                    );

                submitting(
                    submit,
                    true
                );

                try {
                    const facilityId =
                        value(
                            form,
                            "facility_id"
                        );

                    await api(
                        `/api/v1/facilities/${encodeURIComponent(facilityId)}/reservations`,
                        {
                            method:
                                "POST",
                            body:
                                JSON.stringify({
                                    unit_id:
                                        Number(
                                            value(
                                                form,
                                                "unit_id"
                                            )
                                        ),
                                    reservation_date:
                                        value(
                                            form,
                                            "reservation_date"
                                        ),
                                    start_time:
                                        value(
                                            form,
                                            "start_time"
                                        ),
                                    end_time:
                                        value(
                                            form,
                                            "end_time"
                                        ),
                                    description:
                                        value(
                                            form,
                                            "description"
                                        ),
                                }),
                        }
                    );

                    successReload(
                        form,
                        "رزرو با موفقیت ثبت شد."
                    );
                } catch (
                    error
                ) {
                    toast(
                        error.message,
                        "error"
                    );
                } finally {
                    submitting(
                        submit,
                        false
                    );
                }
            }
        );

    $$("[data-open-topup]")
        .forEach(
            (button) => {
                button.addEventListener(
                    "click",
                    () => {
                        const modal =
                            $(
                                "#topUpModal"
                            );

                        if (
                            ! modal
                            || ! window
                                .bootstrap
                        ) {
                            return;
                        }

                        const form =
                            modal.querySelector(
                                '[data-portal-form="topup"]'
                            );

                        form.elements
                            .unit_id
                            .value =
                            button.dataset
                                .unitId;

                        form.elements
                            .building_id
                            .value =
                            button.dataset
                                .buildingId;

                        modal.querySelector(
                            "[data-topup-title]"
                        ).textContent =
                            button.dataset
                                .unitTitle
                            || "واحد";

                        window.bootstrap
                            .Modal
                            .getOrCreateInstance(
                                modal
                            )
                            .show();
                    }
                );
            }
        );

    $(
        '[data-portal-form="topup"]'
    )?.addEventListener(
        "submit",
        async (
            event
        ) => {
            event.preventDefault();

            const form =
                event.currentTarget;

            const submit =
                form.querySelector(
                    '[type="submit"]'
                );

            submitting(
                submit,
                true
            );

            try {
                const stamp =
                    `${Date.now()}-${Math.random().toString(16).slice(2)}`;

                const buildingId =
                    value(
                        form,
                        "building_id"
                    );

                const topup =
                    await api(
                        `/api/v1/buildings/${encodeURIComponent(buildingId)}/wallet-topups`,
                        {
                            method:
                                "POST",
                            body:
                                JSON.stringify({
                                    target_type:
                                        "unit_wallet",
                                    unit_id:
                                        Number(
                                            value(
                                                form,
                                                "unit_id"
                                            )
                                        ),
                                    amount:
                                        Number(
                                            value(
                                                form,
                                                "amount"
                                            )
                                        ),
                                    method:
                                        "online",
                                    gateway:
                                        portal.defaultGateway,
                                    idempotency_key:
                                        `portal-topup:${stamp}`,
                                    description:
                                        "Resident portal wallet top-up",
                                }),
                        }
                    );

                const paymentId =
                    topup
                        ?.data
                        ?.payment_id;

                if (! paymentId) {
                    throw new Error(
                        "شناسه پرداخت ایجاد نشد."
                    );
                }

                const gateway =
                    await api(
                        `/api/v1/payments/${encodeURIComponent(paymentId)}/gateway/initiate`,
                        {
                            method:
                                "POST",
                            body:
                                JSON.stringify({
                                    gateway:
                                        portal.defaultGateway,
                                    idempotency_key:
                                        `portal-gateway:${stamp}`,
                                }),
                        }
                    );

                const redirectUrl =
                    gateway
                        ?.data
                        ?.redirect_url;

                if (redirectUrl) {
                    window.location.href =
                        redirectUrl;
                    return;
                }

                successReload(
                    form,
                    "درخواست افزایش موجودی ایجاد شد."
                );
            } catch (
                error
            ) {
                toast(
                    error.message,
                    "error"
                );
            } finally {
                submitting(
                    submit,
                    false
                );
            }
        }
    );

    /* ---------------------------------------------------------------
       Provider workflow
    ---------------------------------------------------------------- */

    const quoteModal =
        $("#providerQuoteModal");

    $$("[data-provider-quote]")
        .forEach(
            (button) => {
                button.addEventListener(
                    "click",
                    () => {
                        if (
                            ! quoteModal
                            || ! window
                                .bootstrap
                        ) {
                            return;
                        }

                        const form =
                            quoteModal
                                .querySelector(
                                    '[data-portal-form="provider-quote"]'
                                );

                        form.elements
                            .service_request_id
                            .value =
                            button.dataset
                                .serviceId;

                        quoteModal
                            .querySelector(
                                "[data-provider-quote-title]"
                            )
                            .textContent =
                            button.dataset
                                .serviceTitle
                            || "درخواست خدمت";

                        window.bootstrap
                            .Modal
                            .getOrCreateInstance(
                                quoteModal
                            )
                            .show();
                    }
                );
            }
        );

    $(
        '[data-portal-form="provider-quote"]'
    )?.addEventListener(
        "submit",
        async (
            event
        ) => {
            event.preventDefault();

            const form =
                event.currentTarget;

            const submit =
                form.querySelector(
                    '[type="submit"]'
                );

            submitting(
                submit,
                true
            );

            try {
                const serviceId =
                    value(
                        form,
                        "service_request_id"
                    );

                await api(
                    `/api/v1/service-requests/${encodeURIComponent(serviceId)}/quotes`,
                    {
                        method:
                            "POST",
                        body:
                            JSON.stringify({
                                amount:
                                    Number(
                                        value(
                                            form,
                                            "amount"
                                        )
                                    ),
                                notes:
                                    value(
                                        form,
                                        "notes"
                                    ),
                                valid_until:
                                    value(
                                        form,
                                        "valid_until"
                                    ),
                            }),
                    }
                );

                successReload(
                    form,
                    "پیشنهاد قیمت ثبت شد."
                );
            } catch (
                error
            ) {
                toast(
                    error.message,
                    "error"
                );
            } finally {
                submitting(
                    submit,
                    false
                );
            }
        }
    );

    $$("[data-provider-action]")
        .forEach(
            (button) => {
                button.addEventListener(
                    "click",
                    async () => {
                        const action =
                            button.dataset
                                .providerAction;

                        const id =
                            button.dataset
                                .serviceId;

                        const label =
                            action
                            === "start"
                                ? "شروع کار"
                                : "اعلام پایان کار";

                        const confirmed =
                            await confirmAction(
                                label,
                                "این تغییر وضعیت در گردش کار اصلی خدمت ثبت می‌شود."
                            );

                        if (
                            ! confirmed
                        ) {
                            return;
                        }

                        button.disabled =
                            true;

                        try {
                            await api(
                                `/api/v1/service-requests/${encodeURIComponent(id)}/${action}`,
                                {
                                    method:
                                        "POST",
                                    body:
                                        JSON.stringify(
                                            {}
                                        ),
                                }
                            );

                            toast(
                                "وضعیت خدمت بروزرسانی شد."
                            );

                            window.setTimeout(
                                () =>
                                    window.location
                                        .reload(),
                                650
                            );
                        } catch (
                            error
                        ) {
                            toast(
                                error.message,
                                "error"
                            );

                            button.disabled =
                                false;
                        }
                    }
                );
            }
        );

    $(
        '[data-portal-form="provider-bank"]'
    )?.addEventListener(
        "submit",
        async (
            event
        ) => {
            event.preventDefault();

            const form =
                event.currentTarget;

            const submit =
                form.querySelector(
                    '[type="submit"]'
                );

            submitting(
                submit,
                true
            );

            try {
                await api(
                    "/api/v1/provider/bank-accounts",
                    {
                        method:
                            "POST",
                        body:
                            JSON.stringify({
                                bank_name:
                                    value(
                                        form,
                                        "bank_name"
                                    ),
                                account_holder_name:
                                    value(
                                        form,
                                        "account_holder_name"
                                    ),
                                iban:
                                    value(
                                        form,
                                        "iban"
                                    ),
                                account_number:
                                    value(
                                        form,
                                        "account_number"
                                    ),
                                card_number:
                                    value(
                                        form,
                                        "card_number"
                                    ),
                                is_default:
                                    Boolean(
                                        form.elements
                                            .is_default
                                            ?.checked
                                    ),
                            }),
                    }
                );

                successReload(
                    form,
                    "حساب بانکی ثبت شد و پس از تأیید قابل استفاده برای تسویه است."
                );
            } catch (
                error
            ) {
                toast(
                    error.message,
                    "error"
                );
            } finally {
                submitting(
                    submit,
                    false
                );
            }
        }
    );

    $(
        '[data-portal-form="provider-payout"]'
    )?.addEventListener(
        "submit",
        async (
            event
        ) => {
            event.preventDefault();

            const form =
                event.currentTarget;

            const submit =
                form.querySelector(
                    '[type="submit"]'
                );

            const confirmed =
                await confirmAction(
                    "ثبت درخواست تسویه",
                    "مبلغ درخواست از موجودی قابل استفاده کیف پول شما قفل می‌شود."
                );

            if (! confirmed) {
                return;
            }

            submitting(
                submit,
                true
            );

            try {
                await api(
                    "/api/v1/provider/payouts",
                    {
                        method:
                            "POST",
                        body:
                            JSON.stringify({
                                provider_bank_account_id:
                                    Number(
                                        value(
                                            form,
                                            "provider_bank_account_id"
                                        )
                                    ),
                                amount:
                                    Number(
                                        value(
                                            form,
                                            "amount"
                                        )
                                    ),
                                currency:
                                    "IRR",
                            }),
                    }
                );

                successReload(
                    form,
                    "درخواست تسویه ثبت شد."
                );
            } catch (
                error
            ) {
                toast(
                    error.message,
                    "error"
                );
            } finally {
                submitting(
                    submit,
                    false
                );
            }
        }
    );
    /* ---------------------------------------------------------------
       Resident lifecycle — invoice payment
    ---------------------------------------------------------------- */

    const initiateGatewayPayment =
        async (
            paymentId,
            keyPrefix
        ) => {
            const stamp =
                `${Date.now()}-${Math.random().toString(16).slice(2)}`;

            const gateway =
                await api(
                    `/api/v1/payments/${encodeURIComponent(paymentId)}/gateway/initiate`,
                    {
                        method:
                            "POST",
                        body:
                            JSON.stringify({
                                gateway:
                                    portal.defaultGateway,
                                idempotency_key:
                                    `${keyPrefix}:${stamp}`,
                            }),
                    }
                );

            const redirectUrl =
                gateway
                    ?.data
                    ?.redirect_url;

            if (redirectUrl) {
                window.location.href =
                    redirectUrl;

                return true;
            }

            return false;
        };

    const askPaymentAmount =
        async (
            outstanding
        ) => {
            if (window.Swal) {
                const result =
                    await window.Swal.fire({
                        title:
                            "پرداخت صورتحساب",
                        text:
                            "مبلغ پرداخت را وارد کنید.",
                        input:
                            "number",
                        inputValue:
                            outstanding,
                        inputAttributes: {
                            min:
                                "1",
                            max:
                                String(
                                    outstanding
                                ),
                            step:
                                "1",
                            dir:
                                "ltr",
                        },
                        showCancelButton:
                            true,
                        confirmButtonText:
                            "ادامه پرداخت",
                        cancelButtonText:
                            "انصراف",
                        inputValidator:
                            (value) => {
                                const amount =
                                    Number(
                                        value
                                    );

                                if (
                                    ! Number.isInteger(
                                        amount
                                    )
                                    || amount < 1
                                    || amount
                                        > outstanding
                                ) {
                                    return "مبلغ معتبر نیست.";
                                }

                                return undefined;
                            },
                    });

                return result.isConfirmed
                    ? Number(
                        result.value
                    )
                    : null;
            }

            const result =
                window.prompt(
                    "مبلغ پرداخت",
                    String(
                        outstanding
                    )
                );

            if (result === null) {
                return null;
            }

            const amount =
                Number(
                    result
                );

            return Number.isInteger(
                amount
            )
                && amount > 0
                && amount <= outstanding
                    ? amount
                    : null;
        };

    $$("[data-pay-invoice]")
        .forEach(
            (button) => {
                button.addEventListener(
                    "click",
                    async () => {
                        if (
                            ! portal
                                .gatewayEnabled
                        ) {
                            toast(
                                "درگاه پرداخت فعال نیست.",
                                "warning"
                            );
                            return;
                        }

                        const invoiceId =
                            button.dataset
                                .invoiceId;

                        const outstanding =
                            Number(
                                button.dataset
                                    .outstanding
                                || 0
                            );

                        const amount =
                            await askPaymentAmount(
                                outstanding
                            );

                        if (! amount) {
                            return;
                        }

                        button.disabled =
                            true;

                        try {
                            const payment =
                                await api(
                                    `/api/v1/invoices/${encodeURIComponent(invoiceId)}/payments`,
                                    {
                                        method:
                                            "POST",
                                        body:
                                            JSON.stringify({
                                                amount,
                                                method:
                                                    "online",
                                                description:
                                                    `Portal invoice payment ${button.dataset.invoiceNumber || ""}`.trim(),
                                            }),
                                    }
                                );

                            const paymentId =
                                payment
                                    ?.data
                                    ?.id;

                            if (! paymentId) {
                                throw new Error(
                                    "شناسه پرداخت ایجاد نشد."
                                );
                            }

                            const redirected =
                                await initiateGatewayPayment(
                                    paymentId,
                                    `portal-invoice:${invoiceId}`
                                );

                            if (! redirected) {
                                toast(
                                    "پرداخت ایجاد شد اما آدرس انتقال درگاه دریافت نشد.",
                                    "warning"
                                );
                                button.disabled =
                                    false;
                            }
                        } catch (
                            error
                        ) {
                            toast(
                                error.message,
                                "error"
                            );
                            button.disabled =
                                false;
                        }
                    }
                );
            }
        );

    /* ---------------------------------------------------------------
       Resident lifecycle — reservation payment / cancellation
    ---------------------------------------------------------------- */

    const reservationPaymentModal =
        $(
            "#reservationPaymentModal"
        );

    $$("[data-reservation-pay]")
        .forEach(
            (button) => {
                button.addEventListener(
                    "click",
                    () => {
                        if (
                            ! reservationPaymentModal
                            || ! window
                                .bootstrap
                        ) {
                            return;
                        }

                        const form =
                            reservationPaymentModal
                                .querySelector(
                                    '[data-portal-form="reservation-payment"]'
                                );

                        form.elements
                            .reservation_id
                            .value =
                            button.dataset
                                .reservationId;

                        reservationPaymentModal
                            .querySelector(
                                "[data-reservation-payment-title]"
                            )
                            .textContent =
                            button.dataset
                                .reservationTitle
                            || "رزرو";

                        window.bootstrap
                            .Modal
                            .getOrCreateInstance(
                                reservationPaymentModal
                            )
                            .show();
                    }
                );
            }
        );

    $(
        '[data-portal-form="reservation-payment"]'
    )?.addEventListener(
        "submit",
        async (
            event
        ) => {
            event.preventDefault();

            const form =
                event.currentTarget;

            const submit =
                form.querySelector(
                    '[type="submit"]'
                );

            submitting(
                submit,
                true
            );

            try {
                const reservationId =
                    value(
                        form,
                        "reservation_id"
                    );

                await api(
                    `/api/v1/facility-reservations/${encodeURIComponent(reservationId)}/pay`,
                    {
                        method:
                            "POST",
                        body:
                            JSON.stringify({
                                payer_source:
                                    value(
                                        form,
                                        "payer_source"
                                    ),
                            }),
                    }
                );

                successReload(
                    form,
                    "هزینه رزرو از کیف پول پرداخت شد."
                );
            } catch (
                error
            ) {
                toast(
                    error.message,
                    "error"
                );
            } finally {
                submitting(
                    submit,
                    false
                );
            }
        }
    );

    const askCancellationReason =
        async () => {
            if (window.Swal) {
                const result =
                    await window.Swal.fire({
                        title:
                            "لغو رزرو",
                        text:
                            "در صورت وجود، دلیل لغو را ثبت کنید. قوانین جریمه و بازپرداخت ساختمان اعمال می‌شود.",
                        input:
                            "textarea",
                        inputPlaceholder:
                            "دلیل لغو (اختیاری)",
                        showCancelButton:
                            true,
                        confirmButtonText:
                            "لغو رزرو",
                        cancelButtonText:
                            "انصراف",
                        confirmButtonColor:
                            "#c23b49",
                    });

                if (! result.isConfirmed) {
                    return {
                        confirmed:
                            false,
                        reason:
                            null,
                    };
                }

                return {
                    confirmed:
                        true,
                    reason:
                        result.value
                        || null,
                };
            }

            if (
                ! window.confirm(
                    "رزرو لغو شود؟ قوانین جریمه و بازپرداخت اعمال خواهد شد."
                )
            ) {
                return {
                    confirmed:
                        false,
                    reason:
                        null,
                };
            }

            return {
                confirmed:
                    true,
                reason:
                    window.prompt(
                        "دلیل لغو (اختیاری)",
                        ""
                    ),
            };
        };

    $$("[data-reservation-cancel]")
        .forEach(
            (button) => {
                button.addEventListener(
                    "click",
                    async () => {
                        const result =
                            await askCancellationReason();

                        if (
                            ! result
                                .confirmed
                        ) {
                            return;
                        }

                        button.disabled =
                            true;

                        try {
                            await api(
                                `/api/v1/facility-reservations/${encodeURIComponent(button.dataset.reservationId)}/cancel`,
                                {
                                    method:
                                        "POST",
                                    body:
                                        JSON.stringify({
                                            reason:
                                                result
                                                    .reason,
                                        }),
                                }
                            );

                            toast(
                                "رزرو لغو شد. وضعیت بازپرداخت طبق قوانین رزرو ثبت شده است."
                            );

                            window.setTimeout(
                                () =>
                                    window.location
                                        .reload(),
                                650
                            );
                        } catch (
                            error
                        ) {
                            toast(
                                error.message,
                                "error"
                            );
                            button.disabled =
                                false;
                        }
                    }
                );
            }
        );

    /* ---------------------------------------------------------------
       Resident lifecycle — guest cancellation
    ---------------------------------------------------------------- */

    $$("[data-guest-cancel]")
        .forEach(
            (button) => {
                button.addEventListener(
                    "click",
                    async () => {
                        const confirmed =
                            await confirmAction(
                                "لغو دعوت مهمان",
                                "دعوت این مهمان لغو شود؟"
                            );

                        if (! confirmed) {
                            return;
                        }

                        button.disabled =
                            true;

                        try {
                            await api(
                                `/api/v1/guest-visits/${encodeURIComponent(button.dataset.guestVisitId)}/cancel`,
                                {
                                    method:
                                        "POST",
                                    body:
                                        JSON.stringify(
                                            {}
                                        ),
                                }
                            );

                            toast(
                                "دعوت مهمان لغو شد."
                            );

                            window.setTimeout(
                                () =>
                                    window.location
                                        .reload(),
                                650
                            );
                        } catch (
                            error
                        ) {
                            toast(
                                error.message,
                                "error"
                            );
                            button.disabled =
                                false;
                        }
                    }
                );
            }
        );

    /* ---------------------------------------------------------------
       Resident lifecycle — service marketplace
    ---------------------------------------------------------------- */

    const choosePayerSource =
        async (
            title,
            amount = null
        ) => {
            const amountText =
                amount
                    ? ` مبلغ: ${Number(amount).toLocaleString("fa-IR")} IRR`
                    : "";

            if (window.Swal) {
                const result =
                    await window.Swal.fire({
                        title,
                        text:
                            `منبع کیف پول را انتخاب کنید.${amountText}`,
                        input:
                            "select",
                        inputOptions: {
                            unit_wallet:
                                "کیف پول واحد",
                            user_wallet:
                                "کیف پول شخصی",
                        },
                        inputValue:
                            "unit_wallet",
                        showCancelButton:
                            true,
                        confirmButtonText:
                            "تأیید",
                        cancelButtonText:
                            "انصراف",
                    });

                return result.isConfirmed
                    ? result.value
                    : null;
            }

            return "unit_wallet";
        };

    $$("[data-service-quote-accept]")
        .forEach(
            (button) => {
                button.addEventListener(
                    "click",
                    async () => {
                        const payerSource =
                            await choosePayerSource(
                                "پذیرش پیشنهاد خدمت",
                                button.dataset
                                    .quoteAmount
                            );

                        if (! payerSource) {
                            return;
                        }

                        button.disabled =
                            true;

                        try {
                            await api(
                                `/api/v1/service-request-quotes/${encodeURIComponent(button.dataset.quoteId)}/accept`,
                                {
                                    method:
                                        "POST",
                                    body:
                                        JSON.stringify({
                                            payer_source:
                                                payerSource,
                                        }),
                                }
                            );

                            toast(
                                "پیشنهاد پذیرفته شد و مبلغ خدمت در کیف پول قفل شد."
                            );

                            window.setTimeout(
                                () =>
                                    window.location
                                        .reload(),
                                650
                            );
                        } catch (
                            error
                        ) {
                            toast(
                                error.message,
                                "error"
                            );
                            button.disabled =
                                false;
                        }
                    }
                );
            }
        );

    $$("[data-service-confirm]")
        .forEach(
            (button) => {
                button.addEventListener(
                    "click",
                    async () => {
                        const confirmed =
                            await confirmAction(
                                "تأیید پایان خدمت",
                                "با تأیید شما مبلغ قفل‌شده بین ارائه‌دهنده و سهم پلتفرم تسویه می‌شود."
                            );

                        if (! confirmed) {
                            return;
                        }

                        button.disabled =
                            true;

                        try {
                            await api(
                                `/api/v1/service-requests/${encodeURIComponent(button.dataset.serviceId)}/confirm`,
                                {
                                    method:
                                        "POST",
                                    body:
                                        JSON.stringify(
                                            {}
                                        ),
                                }
                            );

                            toast(
                                "پایان خدمت تأیید و تسویه انجام شد."
                            );

                            window.setTimeout(
                                () =>
                                    window.location
                                        .reload(),
                                650
                            );
                        } catch (
                            error
                        ) {
                            toast(
                                error.message,
                                "error"
                            );
                            button.disabled =
                                false;
                        }
                    }
                );
            }
        );

    $$("[data-service-cancel]")
        .forEach(
            (button) => {
                button.addEventListener(
                    "click",
                    async () => {
                        const confirmed =
                            await confirmAction(
                                "لغو درخواست خدمت",
                                "اگر مبلغی قفل شده باشد آزاد می‌شود. درخواست لغو شود؟"
                            );

                        if (! confirmed) {
                            return;
                        }

                        button.disabled =
                            true;

                        try {
                            await api(
                                `/api/v1/service-requests/${encodeURIComponent(button.dataset.serviceId)}/cancel-financial`,
                                {
                                    method:
                                        "POST",
                                    body:
                                        JSON.stringify(
                                            {}
                                        ),
                                }
                            );

                            toast(
                                "درخواست خدمت لغو شد."
                            );

                            window.setTimeout(
                                () =>
                                    window.location
                                        .reload(),
                                650
                            );
                        } catch (
                            error
                        ) {
                            toast(
                                error.message,
                                "error"
                            );
                            button.disabled =
                                false;
                        }
                    }
                );
            }
        );

    /* ---------------------------------------------------------------
       Resident lifecycle — support conversation
    ---------------------------------------------------------------- */

    const ticketConversationModal =
        $("#ticketConversationModal");

    const ticketMessageList =
        ticketConversationModal
            ?.querySelector(
                "[data-ticket-message-list]"
            );

    const ticketReplyForm =
        ticketConversationModal
            ?.querySelector(
                "[data-ticket-reply-form]"
            );

    const renderTicketMessages =
        (messages) => {
            if (! ticketMessageList) {
                return;
            }

            ticketMessageList
                .replaceChildren();

            if (! messages.length) {
                const empty =
                    document.createElement(
                        "div"
                    );

                empty.className =
                    "portal-empty-mini";

                empty.textContent =
                    "هنوز پیامی در این تیکت ثبت نشده است.";

                ticketMessageList
                    .appendChild(
                        empty
                    );

                return;
            }

            messages.forEach(
                (message) => {
                    const item =
                        document.createElement(
                            "div"
                        );

                    const mine =
                        Number(
                            message.user_id
                        )
                        === Number(
                            document.body
                                .dataset
                                .portalUserId
                                || 0
                        );

                    item.className =
                        `portal-ticket-message${mine ? " is-mine" : ""}`;

                    const header =
                        document.createElement(
                            "div"
                        );

                    const author =
                        document.createElement(
                            "strong"
                        );

                    const user =
                        message.user
                        || {};

                    author.textContent =
                        [
                            user.first_name,
                            user.last_name,
                        ]
                            .filter(
                                Boolean
                            )
                            .join(
                                " "
                            )
                        || (
                            mine
                                ? "شما"
                                : "پشتیبانی"
                        );

                    const time =
                        document.createElement(
                            "time"
                        );

                    try {
                        time.textContent =
                            new Intl.DateTimeFormat(
                                "fa-IR-u-ca-persian",
                                {
                                    year:
                                        "numeric",
                                    month:
                                        "2-digit",
                                    day:
                                        "2-digit",
                                    hour:
                                        "2-digit",
                                    minute:
                                        "2-digit",
                                }
                            ).format(
                                new Date(
                                    message
                                        .created_at
                                )
                            );
                    } catch {
                        time.textContent =
                            message.created_at
                            || "";
                    }

                    header.append(
                        author,
                        time
                    );

                    const body =
                        document.createElement(
                            "p"
                        );

                    body.textContent =
                        message.message
                        || "";

                    item.append(
                        header,
                        body
                    );

                    ticketMessageList
                        .appendChild(
                            item
                        );
                }
            );

            ticketMessageList.scrollTop =
                ticketMessageList
                    .scrollHeight;
        };

    const loadTicketConversation =
        async (
            ticketId
        ) => {
            if (! ticketMessageList) {
                return;
            }

            ticketMessageList.innerHTML =
                '<div class="portal-empty-mini">در حال دریافت پیام‌ها...</div>';

            try {
                const response =
                    await api(
                        `/api/v1/support-tickets/${encodeURIComponent(ticketId)}/messages?per_page=100`,
                        {
                            method:
                                "GET",
                        }
                    );

                renderTicketMessages(
                    response
                        ?.data
                    || []
                );
            } catch (
                error
            ) {
                ticketMessageList.innerHTML =
                    "";

                const failed =
                    document.createElement(
                        "div"
                    );

                failed.className =
                    "portal-empty-mini";

                failed.textContent =
                    error.message;

                ticketMessageList
                    .appendChild(
                        failed
                    );
            }
        };

    $$("[data-ticket-conversation]")
        .forEach(
            (button) => {
                button.addEventListener(
                    "click",
                    async () => {
                        if (
                            ! ticketConversationModal
                            || ! window
                                .bootstrap
                        ) {
                            return;
                        }

                        const ticketId =
                            button.dataset
                                .ticketId;

                        const status =
                            button.dataset
                                .ticketStatus;

                        const title =
                            ticketConversationModal
                                .querySelector(
                                    "[data-ticket-conversation-title]"
                                );

                        if (title) {
                            title.textContent =
                                `${button.dataset.ticketNumber || ""} — ${button.dataset.ticketSubject || "گفتگو با پشتیبانی"}`;
                        }

                        if (
                            ticketReplyForm
                        ) {
                            ticketReplyForm
                                .elements
                                .ticket_id
                                .value =
                                ticketId;

                            ticketReplyForm.hidden =
                                [
                                    "resolved",
                                    "closed",
                                ].includes(
                                    status
                                );
                        }

                        window.bootstrap
                            .Modal
                            .getOrCreateInstance(
                                ticketConversationModal
                            )
                            .show();

                        await loadTicketConversation(
                            ticketId
                        );
                    }
                );
            }
        );

    ticketReplyForm
        ?.addEventListener(
            "submit",
            async (
                event
            ) => {
                event.preventDefault();

                const form =
                    event.currentTarget;

                const submit =
                    form.querySelector(
                        '[type="submit"]'
                    );

                submitting(
                    submit,
                    true
                );

                try {
                    const ticketId =
                        value(
                            form,
                            "ticket_id"
                        );

                    await api(
                        `/api/v1/support-tickets/${encodeURIComponent(ticketId)}/messages`,
                        {
                            method:
                                "POST",
                            body:
                                JSON.stringify({
                                    message:
                                        value(
                                            form,
                                            "message"
                                        ),
                                    is_internal:
                                        false,
                                }),
                        }
                    );

                    form.elements
                        .message
                        .value =
                        "";

                    await loadTicketConversation(
                        ticketId
                    );

                    toast(
                        "پیام ارسال شد."
                    );
                } catch (
                    error
                ) {
                    toast(
                        error.message,
                        "error"
                    );
                } finally {
                    submitting(
                        submit,
                        false
                    );
                }
            }
        );

    $$("[data-ticket-reopen]")
        .forEach(
            (button) => {
                button.addEventListener(
                    "click",
                    async () => {
                        const confirmed =
                            await confirmAction(
                                "بازگشایی تیکت",
                                "این تیکت دوباره باز شود؟"
                            );

                        if (! confirmed) {
                            return;
                        }

                        button.disabled =
                            true;

                        try {
                            await api(
                                `/api/v1/support-tickets/${encodeURIComponent(button.dataset.ticketId)}/reopen`,
                                {
                                    method:
                                        "POST",
                                    body:
                                        JSON.stringify(
                                            {}
                                        ),
                                }
                            );

                            toast(
                                "تیکت بازگشایی شد."
                            );

                            window.setTimeout(
                                () =>
                                    window.location
                                        .reload(),
                                650
                            );
                        } catch (
                            error
                        ) {
                            toast(
                                error.message,
                                "error"
                            );
                            button.disabled =
                                false;
                        }
                    }
                );
            }
        );

    $$('[data-loyalty-claim]')
        .forEach(
            (button) => {
                button.addEventListener(
                    "click",
                    async () => {
                        const confirmed =
                            await confirmAction(
                                "دریافت جایزه",
                                "امتیاز لازم از حساب شما کسر و درخواست جایزه ثبت شود؟"
                            );

                        if (! confirmed) {
                            return;
                        }

                        button.disabled = true;

                        try {
                            await api(
                                `/api/v1/loyalty/rewards/${encodeURIComponent(button.dataset.rewardId)}/claims`,
                                {
                                    method: "POST",
                                    body: JSON.stringify({
                                        idempotency_key:
                                            `portal-${button.dataset.rewardId}-${Date.now()}-${Math.random().toString(16).slice(2)}`,
                                    }),
                                }
                            );

                            toast(
                                "درخواست جایزه ثبت شد."
                            );

                            window.setTimeout(
                                () => window.location.reload(),
                                650
                            );
                        } catch (error) {
                            toast(
                                error.message,
                                "error"
                            );
                            button.disabled = false;
                        }
                    }
                );
            }
        );

})();
