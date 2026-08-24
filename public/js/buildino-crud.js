
(() => {
    "use strict";

    const bootstrap = window.BuildinoCrud;

    if (! bootstrap) {
        return;
    }

    const resource = bootstrap.resource || {};
    const app = document.getElementById("buildinoCrudApp");

    if (! app) {
        return;
    }

    const $ = (selector, root = document) =>
        root.querySelector(selector);

    const $$ = (selector, root = document) =>
        [...root.querySelectorAll(selector)];

    const elements = {
        contextFields: $("#crudContextFields"),
        createButton: $("#crudCreateButton"),
        refreshButton: $("#crudRefreshButton"),
        search: $("#crudSearch"),
        state: $("#crudState"),
        tableWrap: $("#crudTableWrap"),
        tableHead: $("#crudTableHead"),
        tableBody: $("#crudTableBody"),
        drawer: $("#crudDrawer"),
        drawerBackdrop: $("#crudDrawerBackdrop"),
        drawerClose: $("#crudDrawerClose"),
        cancelButton: $("#crudCancelButton"),
        drawerTitle: $("#crudDrawerTitle"),
        drawerEyebrow: $("#crudDrawerEyebrow"),
        form: $("#crudForm"),
        formFields: $("#crudFormFields"),
        formError: $("#crudFormError"),
        recordId: $("#crudRecordId"),
        saveButton: $("#crudSaveButton"),
        singletonForm: $("#crudSingletonForm"),
        singletonFields: $("#crudSingletonFields"),
        loadSingleton: $("#crudLoadSingleton"),
        actionModal: $("#crudActionModal"),
        actionBackdrop: $("#crudActionBackdrop"),
        actionClose: $("#crudActionClose"),
        actionCancel: $("#crudActionCancel"),
        actionTitle: $("#crudActionTitle"),
        actionForm: $("#crudActionForm"),
        actionFields: $("#crudActionFields"),
        actionError: $("#crudActionError"),
        actionSubmit: $("#crudActionSubmit"),
        toastStack: $("#crudToastStack"),
    };

    const state = {
        rows: [],
        filteredRows: [],
        currentPage: 1,
        pageSize: 25,
        editRow: null,
        action: null,
        actionRow: null,
        loading: false,
    };

    const escapeHtml = (value) => {
        const div = document.createElement("div");
        div.textContent = value ?? "";
        return div.innerHTML;
    };

    const getByPath = (object, path) => {
        if (! object || ! path) {
            return null;
        }

        return path
            .split(".")
            .reduce(
                (value, key) =>
                    value === null
                    || value === undefined
                        ? null
                        : value[key],
                object
            );
    };

    const setByPath = (object, path, value) => {
        const parts = path.split(".");
        let cursor = object;

        parts.forEach((part, index) => {
            if (index === parts.length - 1) {
                cursor[part] = value;
                return;
            }

            if (
                typeof cursor[part] !== "object"
                || cursor[part] === null
                || Array.isArray(cursor[part])
            ) {
                cursor[part] = {};
            }

            cursor = cursor[part];
        });
    };

    const humanBoolean = (value) =>
        value ? "بله" : "خیر";

    const formatValue = (value) => {
        if (value === null || value === undefined || value === "") {
            return "—";
        }

        if (typeof value === "boolean") {
            return humanBoolean(value);
        }

        if (Array.isArray(value)) {
            if (! value.length) {
                return "—";
            }

            return value
                .map((item) => {
                    if (typeof item === "object" && item !== null) {
                        return (
                            item.label
                            || item.title
                            || item.name
                            || item.display_name
                            || item.id
                            || JSON.stringify(item)
                        );
                    }

                    return item;
                })
                .join("، ");
        }

        if (typeof value === "object") {
            return (
                value.title
                || value.name
                || value.display_name
                || value.label
                || value.id
                || JSON.stringify(value)
            );
        }

        const text = String(value);

        if (
            /^\d{4}-\d{2}-\d{2}T/.test(text)
        ) {
            return text
                .replace("T", " ")
                .replace(/\.\d+Z$/, "")
                .replace(/Z$/, "");
        }

        return text;
    };

    const statusTone = (value) => {
        const status = String(value || "").toLowerCase();

        if (
            [
                "active",
                "approved",
                "confirmed",
                "completed",
                "paid",
                "posted",
                "resolved",
                "closed",
                "sent",
                "delivered",
                "verified",
            ].includes(status)
        ) {
            return "success";
        }

        if (
            [
                "pending",
                "open",
                "assigned",
                "in_progress",
                "waiting_user",
                "processing",
                "partial",
                "issued",
                "draft",
                "payment_pending",
                "awaiting_confirmation",
            ].includes(status)
        ) {
            return "warning";
        }

        if (
            [
                "failed",
                "rejected",
                "blocked",
                "overdue",
            ].includes(status)
        ) {
            return "danger";
        }

        if (
            [
                "cancelled",
                "expired",
                "void",
                "inactive",
            ].includes(status)
        ) {
            return "muted";
        }

        return "info";
    };

    const isStatusColumn = (key) =>
        key === "status"
        || key === "is_active"
        || key === "is_blocked"
        || key === "is_verified"
        || key === "is_system"
        || key === "is_primary"
        || key === "is_default";

    const formatCell = (column, row) => {
        const value = getByPath(
            row,
            column.key
        );

        if (
            [
                "is_active",
                "is_blocked",
                "is_verified",
                "is_system",
                "is_primary",
                "is_default",
            ].includes(column.key)
        ) {
            const boolValue =
                value === true
                || value === 1
                || value === "1";

            return `
                <span class="crud-status crud-status--${boolValue ? "success" : "muted"}">
                    ${boolValue ? "بله" : "خیر"}
                </span>
            `;
        }

        if (
            column.key === "status"
            || column.key.endsWith("_status")
        ) {
            return `
                <span class="crud-status crud-status--${statusTone(value)}">
                    ${escapeHtml(formatValue(value))}
                </span>
            `;
        }

        let formatted = formatValue(value);

        const key =
            String(
                column.key
                || ""
            ).toLowerCase();

        if (
            value !== null
            && value !== undefined
            && window.BuildinoUI
        ) {
            if (
                /(^|_)(date|at)$/.test(key)
                || key.endsWith("_at")
                || key.endsWith("_date")
            ) {
                formatted =
                    key.endsWith("_at")
                        ? window
                            .BuildinoUI
                            .dateTime(value)
                        : window
                            .BuildinoUI
                            .date(value);
            } else if (
                /amount|balance|price|fee|commission|gmv|total/.test(
                    key
                )
                && ! Number.isNaN(
                    Number(value)
                )
            ) {
                formatted =
                    window
                        .BuildinoUI
                        .number(value);
            }
        }

        return `<span title="${escapeHtml(formatted)}">${escapeHtml(formatted)}</span>`;
    };

    const contextValues = () => {
        const values = {};

        $$("[data-context-name]").forEach((select) => {
            values[
                select.dataset.contextName
            ] = select.value;
        });

        return values;
    };

    const interpolate = (
        template,
        row = null
    ) => {
        if (! template) {
            return null;
        }

        const values = {
            ...contextValues(),
            id: row?.id ?? state.editRow?.id ?? "",
        };

        return template.replace(
            /\{([^}]+)\}/g,
            (match, name) => {
                let value =
                    values[name];

                if (
                    (value === undefined
                    || value === null
                    || value === "")
                    && row
                ) {
                    value = getByPath(
                        row,
                        name
                    );
                }

                if (
                    value === undefined
                    || value === null
                    || value === ""
                ) {
                    throw new Error(
                        `مقدار «${name}» برای این عملیات انتخاب نشده است.`
                    );
                }

                return encodeURIComponent(
                    String(value)
                );
            }
        );
    };

    const apiFetch = async (
        url,
        options = {}
    ) => {
        const headers = {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
            "X-CSRF-TOKEN": bootstrap.csrfToken,
            ...(options.headers || {}),
        };

        if (
            options.body
            && ! (
                options.body
                instanceof FormData
            )
        ) {
            headers["Content-Type"] =
                "application/json";
        }

        const response = await fetch(
            url,
            {
                credentials: "same-origin",
                ...options,
                headers,
            }
        );

        const contentType =
            response.headers.get(
                "content-type"
            ) || "";

        let payload = null;

        if (
            response.status !== 204
            && contentType.includes(
                "application/json"
            )
        ) {
            payload = await response.json();
        } else if (
            response.status !== 204
        ) {
            payload = await response.text();
        }

        if (! response.ok) {
            const error = new Error(
                responseMessage(
                    payload,
                    response.status
                )
            );

            error.status =
                response.status;
            error.payload =
                payload;

            throw error;
        }

        return {
            response,
            payload,
        };
    };

    const responseMessage = (
        payload,
        status
    ) => {
        if (! payload) {
            return `خطای HTTP ${status}`;
        }

        if (typeof payload === "string") {
            return payload;
        }

        if (payload.message) {
            return payload.message;
        }

        if (payload.errors) {
            return Object.values(
                payload.errors
            )
                .flat()
                .join(" | ");
        }

        return `خطای HTTP ${status}`;
    };

    const rowsFromPayload = (payload) => {
        if (! payload) {
            return [];
        }

        if (Array.isArray(payload)) {
            return payload;
        }

        if (Array.isArray(payload.data)) {
            return payload.data;
        }

        if (
            payload.data
            && typeof payload.data === "object"
        ) {
            return [payload.data];
        }

        return [];
    };

    const transformRows = (rows) => {
        if (
            resource.list_transform
            !== "schedule_time_slots"
        ) {
            return rows;
        }

        const scheduleId =
            contextValues()
                .facility_schedule_id;

        const schedules = scheduleId
            ? rows.filter(
                (schedule) =>
                    String(schedule.id)
                    === String(scheduleId)
            )
            : rows;

        return schedules.flatMap(
            (schedule) =>
                (schedule.time_slots || [])
                    .map((slot) => ({
                        ...slot,
                        schedule_day_of_week:
                            schedule.day_of_week,
                        schedule_start_time:
                            schedule.start_time,
                        schedule_end_time:
                            schedule.end_time,
                    }))
        );
    };

    const setLoading = (
        loading,
        message = "در حال بارگذاری..."
    ) => {
        state.loading = loading;

        if (! elements.state) {
            return;
        }

        if (loading) {
            elements.state.hidden = false;
            elements.state.innerHTML = `
                <div class="crud-state__loader"></div>
                <span>${escapeHtml(message)}</span>
            `;

            if (elements.tableWrap) {
                elements.tableWrap.hidden = true;
            }

            return;
        }

        elements.state.hidden = true;
    };

    const showState = (
        message,
        tone = "muted"
    ) => {
        if (! elements.state) {
            return;
        }

        elements.state.hidden = false;
        elements.state.innerHTML = `
            <div class="crud-empty crud-empty--${tone}">
                ${escapeHtml(message)}
            </div>
        `;

        if (elements.tableWrap) {
            elements.tableWrap.hidden = true;
        }

    };

    const toast = (
        message,
        tone = "success"
    ) => {
        if (
            window.BuildinoUI
            && typeof window.BuildinoUI.toast
                === "function"
            && window.Swal
        ) {
            window.BuildinoUI
                .toast(
                    message,
                    tone
                );

            return;
        }

        if (! elements.toastStack) {
            return;
        }

        const item =
            document.createElement(
                "div"
            );

        item.className =
            `crud-toast crud-toast--${tone}`;

        item.innerHTML = `
            <span>${escapeHtml(message)}</span>
            <button type="button" aria-label="بستن">×</button>
        `;

        item
            .querySelector("button")
            .addEventListener(
                "click",
                () => item.remove()
            );

        elements.toastStack
            .appendChild(item);

        window.setTimeout(
            () => item.remove(),
            5000
        );
    };

    const confirmAction = async (
        message,
        {
            confirmText =
                "بله، ادامه بده",
            cancelText =
                "انصراف",
            tone =
                "warning",
        } = {}
    ) => {
        if (
            window.Swal
            && typeof window.Swal.fire
                === "function"
        ) {
            const result =
                await window.Swal.fire({
                    title:
                        "تأیید عملیات",
                    text:
                        String(
                            message
                            || "آیا مطمئن هستید؟"
                        ),
                    icon:
                        tone === "danger"
                            ? "warning"
                            : tone,
                    showCancelButton:
                        true,
                    confirmButtonText:
                        confirmText,
                    cancelButtonText:
                        cancelText,
                    reverseButtons:
                        true,
                    focusCancel:
                        true,
                    customClass: {
                        popup:
                            "buildino-swal-popup",
                        confirmButton:
                            "buildino-swal-confirm",
                        cancelButton:
                            "buildino-swal-cancel",
                    },
                });

            return Boolean(
                result.isConfirmed
            );
        }

        return window.confirm(
            message
        );
    };

    const validateRequiredContext = (
        forTemplate = null
    ) => {
        const definitions =
            resource.context || [];

        for (const definition of definitions) {
            if (
                ! definition.required
            ) {
                continue;
            }

            const select = $(
                `[data-context-name="${CSS.escape(definition.name)}"]`
            );

            if (
                ! select
                || ! select.value
            ) {
                return {
                    valid: false,
                    message:
                        `ابتدا «${definition.label}» را انتخاب کنید.`,
                };
            }
        }

        if (forTemplate) {
            try {
                interpolate(
                    forTemplate
                );
            } catch (error) {
                return {
                    valid: false,
                    message: error.message,
                };
            }
        }

        return {
            valid: true,
        };
    };

    const lookupUrl = (
        source,
        field = null
    ) => {
        const url = new URL(
            `${bootstrap.lookupBase}/${encodeURIComponent(source)}`,
            window.location.origin
        );

        const contexts =
            contextValues();

        Object.entries(contexts)
            .forEach(
                ([key, value]) => {
                    if (value !== "") {
                        url.searchParams.set(
                            key,
                            value
                        );
                    }
                }
            );

        if (
            field?.depends_on
        ) {
            const dependent =
                $(
                    `[name="${CSS.escape(field.depends_on)}"]`
                )
                || $(
                    `[data-context-name="${CSS.escape(field.depends_on)}"]`
                );

            if (dependent?.value) {
                url.searchParams.set(
                    field.depends_on,
                    dependent.value
                );
            }
        }

        return url.toString();
    };

    const refreshLookupSelect = (
        select
    ) => {
        window.BuildinoSelect2
            ?.refresh(select);
    };

    const loadLookupOptions = async (
        select,
        field,
        selectedValue = null
    ) => {
        if (! field.lookup) {
            return;
        }

        const placeholder =
            field.multiple
                || field.type === "multiselect"
                    ? ""
                    : `<option value="">انتخاب کنید</option>`;

        select.innerHTML =
            placeholder
            + `<option value="" disabled>در حال بارگذاری...</option>`;

        refreshLookupSelect(
            select
        );

        try {
            const { payload } =
                await apiFetch(
                    lookupUrl(
                        field.lookup,
                        field
                    )
                );

            const rows =
                rowsFromPayload(
                    payload
                );

            select.innerHTML =
                placeholder;

            rows.forEach((item) => {
                const option =
                    document.createElement(
                        "option"
                    );

                option.value =
                    item.id;
                option.textContent =
                    item.label
                    ?? item.title
                    ?? item.name
                    ?? item.id;

                const values =
                    Array.isArray(
                        selectedValue
                    )
                        ? selectedValue.map(
                            String
                        )
                        : [
                            String(
                                selectedValue
                                ?? ""
                            ),
                        ];

                option.selected =
                    values.includes(
                        String(
                            item.id
                        )
                    );

                select.appendChild(
                    option
                );
            });

            refreshLookupSelect(
                select
            );
        } catch (error) {
            select.innerHTML =
                placeholder
                + `<option value="" disabled>خطا در دریافت گزینه‌ها</option>`;

            refreshLookupSelect(
                select
            );
        }
    };

    const initContextLookups = async () => {
        const selects =
            $$("[data-context-name]");

        for (const select of selects) {
            const field = {
                name:
                    select.dataset
                        .contextName,
                lookup:
                    select.dataset
                        .lookup,
                depends_on:
                    select.dataset
                        .dependsOn
                    || null,
            };

            await loadLookupOptions(
                select,
                field,
                select.value
            );
        }

        selects.forEach((select) => {
            select.addEventListener(
                "change",
                async () => {
                    const name =
                        select.dataset
                            .contextName;

                    for (
                        const dependent
                        of selects
                    ) {
                        if (
                            dependent
                                .dataset
                                .dependsOn
                            === name
                        ) {
                            dependent.value =
                                "";

                            await loadLookupOptions(
                                dependent,
                                {
                                    name:
                                        dependent
                                            .dataset
                                            .contextName,
                                    lookup:
                                        dependent
                                            .dataset
                                            .lookup,
                                    depends_on:
                                        dependent
                                            .dataset
                                            .dependsOn,
                                }
                            );
                        }
                    }

                    state.currentPage = 1;

                    if (
                        resource.mode
                        !== "singleton"
                        && resource.list
                    ) {
                        await loadRows();
                    }
                }
            );
        });
    };

    const listEndpoint = () => {
        if (! resource.list?.url) {
            return null;
        }

        return interpolate(
            resource.list.url
        );
    };

    const loadRows = async () => {
        if (! resource.list) {
            showState(
                "برای این صفحه فهرست جداگانه تعریف نشده است."
            );
            return;
        }

        let endpoint = null;

        try {
            endpoint =
                listEndpoint();
        } catch (error) {
            showState(
                error.message
            );
            return;
        }

        setLoading(true);

        try {
            const { payload } =
                await apiFetch(
                    endpoint,
                    {
                        method:
                            resource
                                .list
                                .method
                            || "GET",
                    }
                );

            state.rows =
                transformRows(
                    rowsFromPayload(
                        payload
                    )
                );

            state.currentPage = 1;

            applySearch();
            setLoading(false);

            if (! state.rows.length) {
                showState(
                    "رکوردی برای نمایش وجود ندارد."
                );
            }
        } catch (error) {
            showState(
                error.message,
                "danger"
            );
        }
    };

    const applySearch = () => {
        const term =
            (
                elements.search
                    ?.value
                || ""
            )
                .trim()
                .toLocaleLowerCase(
                    "fa"
                );

        if (! term) {
            state.filteredRows =
                [...state.rows];
        } else {
            state.filteredRows =
                state.rows.filter(
                    (row) =>
                        JSON.stringify(
                            row
                        )
                            .toLocaleLowerCase(
                                "fa"
                            )
                            .includes(
                                term
                            )
                );
        }

        const maxPage =
            Math.max(
                1,
                Math.ceil(
                    state.filteredRows.length
                    / state.pageSize
                )
            );

        state.currentPage =
            Math.min(
                state.currentPage,
                maxPage
            );

        renderTable();
    };

    const renderTable = () => {
        if (
            ! elements.tableHead
            || ! elements.tableBody
        ) {
            return;
        }

        const columns =
            resource.columns || [];

        elements.tableHead.innerHTML =
            columns
                .map(
                    (column) =>
                        `<th>${escapeHtml(column.label)}</th>`
                )
                .join("")
            + `<th class="crud-actions-column">عملیات</th>`;

        const start =
            (state.currentPage - 1)
            * state.pageSize;

        const rows =
            state.filteredRows.slice(
                start,
                start + state.pageSize
            );

        elements.tableBody.innerHTML =
            "";

        rows.forEach((row) => {
            const tr =
                document.createElement(
                    "tr"
                );

            const cells =
                columns.map(
                    (column) =>
                        `<td>${formatCell(column, row)}</td>`
                );

            const actions = [];

            if (resource.update) {
                actions.push(`
                    <button
                        type="button"
                        class="crud-row-action crud-row-action--edit"
                        data-row-action="edit"
                        data-id="${escapeHtml(row.id)}"
                    >
                        ویرایش
                    </button>
                `);
            }

            (resource.actions || [])
                .forEach((action) => {
                    actions.push(`
                        <button
                            type="button"
                            class="crud-row-action crud-row-action--${escapeHtml(action.tone || "primary")}"
                            data-special-action="${escapeHtml(action.key)}"
                            data-id="${escapeHtml(row.id)}"
                        >
                            ${escapeHtml(action.title)}
                        </button>
                    `);
                });

            if (resource.delete) {
                actions.push(`
                    <button
                        type="button"
                        class="crud-row-action crud-row-action--danger"
                        data-row-action="delete"
                        data-id="${escapeHtml(row.id)}"
                    >
                        حذف
                    </button>
                `);
            }

            tr.innerHTML =
                cells.join("")
                + `
                    <td class="crud-actions-column">
                        <div class="crud-row-actions">
                            ${actions.join("") || "—"}
                        </div>
                    </td>
                `;

            elements.tableBody
                .appendChild(tr);
        });

        elements.tableWrap.hidden =
            false;

        bindTableActions();
    };

    const findRow = (id) =>
        state.rows.find(
            (row) =>
                String(row.id)
                === String(id)
        );

    let formulaBuilderSequence = 0;

    const formulaMethods = [
        {
            value: "fixed",
            title: "مبلغ ثابت برای هر واحد",
            description: "همه واحدها مبلغ یکسانی پرداخت می‌کنند.",
            amountLabel: "مبلغ هر واحد (ریال)",
        },
        {
            value: "area",
            title: "بر اساس متراژ",
            description: "نرخ هر مترمربع در متراژ واحد ضرب می‌شود.",
            amountLabel: "نرخ هر مترمربع (ریال)",
        },
        {
            value: "persons",
            title: "بر اساس تعداد ساکنان",
            description: "نرخ هر نفر در تعداد ساکنان فعال ضرب می‌شود.",
            amountLabel: "نرخ هر نفر (ریال)",
        },
    ];

    const formulaNumber = (value) =>
        new Intl.NumberFormat("fa-IR")
            .format(Number(value) || 0);

    const parseFormulaJson = (
        value,
        label,
        fallback
    ) => {
        const trimmed = String(value || "")
            .trim();

        if (! trimmed) {
            return fallback;
        }

        try {
            return JSON.parse(trimmed);
        } catch (error) {
            throw new Error(
                `مقدار «${label}» JSON معتبر نیست.`
            );
        }
    };

    const collectChargeFormulaBuilder = (
        component
    ) => {
        if (
            component.dataset.builderMode
            === "advanced"
        ) {
            const calculationType = $(
                "[data-formula-advanced-type]",
                component
            )?.value;
            const configuration =
                parseFormulaJson(
                    $(
                        "[data-formula-advanced-configuration]",
                        component
                    )?.value,
                    "تنظیمات پیشرفته فرمول",
                    null
                );
            const items = parseFormulaJson(
                $(
                    "[data-formula-advanced-items]",
                    component
                )?.value,
                "آیتم‌های پیشرفته فرمول",
                []
            );

            if (! calculationType) {
                throw new Error(
                    "روش محاسبه پیشرفته را انتخاب کنید."
                );
            }

            if (
                ! Array.isArray(items)
                || ! items.length
            ) {
                throw new Error(
                    "فرمول باید دست‌کم یک آیتم هزینه داشته باشد."
                );
            }

            return {
                calculation_type:
                    calculationType,
                configuration,
                items,
            };
        }

        const calculationType = $(
            "[data-formula-method]:checked",
            component
        )?.value;

        if (! calculationType) {
            throw new Error(
                "روش محاسبه شارژ را انتخاب کنید."
            );
        }

        const items = $$([
            "[data-formula-item]",
        ].join(""), component).map(
            (row) => {
                const title = String(
                    $(
                        "[data-formula-item-title]",
                        row
                    )?.value
                    || ""
                ).trim();
                const rawAmount = $(
                    "[data-formula-item-amount]",
                    row
                )?.value;
                const baseAmount =
                    Number(rawAmount);
                const categoryValue = $(
                    "[data-formula-item-category]",
                    row
                )?.value;

                if (! title) {
                    throw new Error(
                        "عنوان همه ردیف‌های هزینه را وارد کنید."
                    );
                }

                if (
                    rawAmount === ""
                    || ! Number.isSafeInteger(
                        baseAmount
                    )
                    || baseAmount < 0
                ) {
                    throw new Error(
                        `مبلغ «${title}» باید عدد صحیح و مثبت یا صفر باشد.`
                    );
                }

                return {
                    financial_category_id:
                        categoryValue
                            ? Number(
                                categoryValue
                            )
                            : null,
                    title,
                    base_amount:
                        baseAmount,
                };
            }
        );

        if (! items.length) {
            throw new Error(
                "فرمول باید دست‌کم یک ردیف هزینه داشته باشد."
            );
        }

        return {
            builder: {
                calculation_type:
                    calculationType,
                items,
            },
        };
    };

    const createChargeFormulaBuilder = async (
        field,
        row = null
    ) => {
        formulaBuilderSequence += 1;

        const component = document.createElement(
            "section"
        );
        const methodName =
            `charge-formula-method-${formulaBuilderSequence}`;
        const currentType =
            String(
                row?.calculation_type
                || "fixed"
            );
        const guidedType =
            formulaMethods.some(
                (method) =>
                    method.value === currentType
            )
                ? currentType
                : "fixed";
        const startsAdvanced =
            Boolean(row)
            && ! formulaMethods.some(
                (method) =>
                    method.value === currentType
            );

        component.className =
            "crud-field crud-field--wide crud-formula-builder";
        component.dataset.chargeFormulaBuilder =
            field.name;
        component.dataset.builderMode =
            startsAdvanced
                ? "advanced"
                : "guided";
        component.innerHTML = `
            <div class="crud-formula-builder__heading">
                <div>
                    <strong>${escapeHtml(field.label)}</strong>
                    <p>روش محاسبه را انتخاب و ردیف‌های هزینه را وارد کنید؛ فرمول به‌صورت خودکار ساخته می‌شود.</p>
                </div>
                <button
                    type="button"
                    class="crud-formula-builder__advanced-button"
                    data-formula-advanced-toggle
                >
                    ${startsAdvanced ? "بازگشت به فرمول‌ساز ساده" : "تنظیمات پیشرفته"}
                </button>
            </div>

            <div data-formula-guided ${startsAdvanced ? "hidden" : ""}>
                <div class="crud-formula-methods">
                    ${formulaMethods.map((method) => `
                        <label class="crud-formula-method">
                            <input
                                type="radio"
                                name="${methodName}"
                                value="${method.value}"
                                data-formula-method
                                ${method.value === guidedType ? "checked" : ""}
                            >
                            <span>
                                <b>${method.title}</b>
                                <small>${method.description}</small>
                            </span>
                        </label>
                    `).join("")}
                </div>

                <div class="crud-formula-items-heading">
                    <div>
                        <strong>ردیف‌های هزینه</strong>
                        <small>برای نمونه می‌توانید شارژ پایه، آب مشاع و نظافت را جدا وارد کنید.</small>
                    </div>
                    <button type="button" data-formula-add-item>
                        + افزودن ردیف
                    </button>
                </div>
                <div class="crud-formula-items" data-formula-items></div>

                <div class="crud-formula-preview">
                    <div>
                        <small>فرمول تولیدشده</small>
                        <strong data-formula-expression></strong>
                    </div>
                    <label data-formula-example-wrap>
                        <span data-formula-example-label></span>
                        <input
                            type="number"
                            min="0"
                            step="1"
                            value="100"
                            data-formula-example
                        >
                    </label>
                    <div>
                        <small>مبلغ نمونه</small>
                        <strong data-formula-example-result></strong>
                    </div>
                </div>
            </div>

            <div class="crud-formula-advanced" data-formula-advanced ${startsAdvanced ? "" : "hidden"}>
                <p>این بخش فقط برای فرمول‌های قدیمی یا سناریوهای سفارشی است. در استفاده معمول، فرمول‌ساز ساده پیشنهاد می‌شود.</p>
                <label>
                    <span>روش محاسبه</span>
                    <select data-formula-advanced-type>
                        <option value="fixed">ثابت برای هر واحد</option>
                        <option value="area">بر اساس متراژ</option>
                        <option value="persons">بر اساس تعداد نفرات</option>
                        <option value="equal">مساوی</option>
                        <option value="custom">سفارشی</option>
                    </select>
                </label>
                <label>
                    <span>تنظیمات فرمول (JSON)</span>
                    <textarea rows="6" dir="ltr" data-formula-advanced-configuration></textarea>
                </label>
                <label>
                    <span>آیتم‌های فرمول (JSON)</span>
                    <textarea rows="10" dir="ltr" data-formula-advanced-items></textarea>
                </label>
            </div>
        `;

        const itemsContainer = $(
            "[data-formula-items]",
            component
        );
        const advancedType = $(
            "[data-formula-advanced-type]",
            component
        );
        const advancedConfiguration = $(
            "[data-formula-advanced-configuration]",
            component
        );
        const advancedItems = $(
            "[data-formula-advanced-items]",
            component
        );

        advancedType.value = currentType;
        advancedConfiguration.value = JSON.stringify(
            row?.configuration || {},
            null,
            2
        );
        advancedItems.value = JSON.stringify(
            row?.items?.length
                ? row.items.map((item) => ({
                    financial_category_id:
                        item.financial_category_id,
                    title: item.title,
                    base_amount:
                        item.base_amount,
                    configuration:
                        item.configuration || {},
                }))
                : [
                    {
                        financial_category_id:
                            null,
                        title: "شارژ پایه",
                        base_amount: 0,
                        configuration: {},
                    },
                ],
            null,
            2
        );

        const selectedMethod = () =>
            $(
                "[data-formula-method]:checked",
                component
            )?.value
            || "fixed";

        const updatePreview = () => {
            const method = selectedMethod();
            const definition =
                formulaMethods.find(
                    (item) =>
                        item.value === method
                )
                || formulaMethods[0];
            const amounts = $$([
                "[data-formula-item-amount]",
            ].join(""), component);
            const total = amounts.reduce(
                (sum, input) =>
                    sum
                    + (
                        Number(input.value)
                        || 0
                    ),
                0
            );
            const exampleInput = $(
                "[data-formula-example]",
                component
            );
            const exampleWrap = $(
                "[data-formula-example-wrap]",
                component
            );
            const exampleLabel = $(
                "[data-formula-example-label]",
                component
            );

            if (
                method === "persons"
                && Number(exampleInput.value) === 100
            ) {
                exampleInput.value = 4;
            }

            const multiplier =
                method === "fixed"
                    ? 1
                    : Number(
                        exampleInput.value
                    ) || 0;
            const expression =
                method === "fixed"
                    ? `${formulaNumber(total)} ریال برای هر واحد`
                    : method === "area"
                        ? `${formulaNumber(total)} ریال × متراژ واحد`
                        : `${formulaNumber(total)} ریال × تعداد ساکنان`;

            $(
                "[data-formula-expression]",
                component
            ).textContent = expression;
            $(
                "[data-formula-example-result]",
                component
            ).textContent =
                `${formulaNumber(total * multiplier)} ریال`;

            exampleWrap.hidden =
                method === "fixed";
            exampleLabel.textContent =
                method === "area"
                    ? "متراژ نمونه"
                    : "تعداد نفر نمونه";
            $$([
                "[data-formula-item-amount-label]",
            ].join(""), component).forEach(
                (label) => {
                    label.textContent =
                        definition.amountLabel;
                }
            );
        };

        const addItem = async (item = {}) => {
            const itemRow = document.createElement(
                "div"
            );
            itemRow.className =
                "crud-formula-item";
            itemRow.dataset.formulaItem = "";
            itemRow.innerHTML = `
                <label>
                    <span>عنوان هزینه</span>
                    <input
                        type="text"
                        maxlength="255"
                        required
                        placeholder="مثلاً شارژ پایه"
                        data-formula-item-title
                    >
                </label>
                <label>
                    <span data-formula-item-amount-label></span>
                    <input
                        type="number"
                        min="0"
                        step="1"
                        required
                        dir="ltr"
                        placeholder="0"
                        data-formula-item-amount
                    >
                </label>
                <label>
                    <span>دسته مالی (اختیاری)</span>
                    <select data-formula-item-category></select>
                </label>
                <button
                    type="button"
                    class="crud-formula-item__remove"
                    title="حذف ردیف"
                    data-formula-remove-item
                >×</button>
            `;

            $(
                "[data-formula-item-title]",
                itemRow
            ).value = item.title || "";
            $(
                "[data-formula-item-amount]",
                itemRow
            ).value = item.base_amount ?? 0;

            itemsContainer.appendChild(itemRow);

            const categorySelect = $(
                "[data-formula-item-category]",
                itemRow
            );

            await loadLookupOptions(
                categorySelect,
                {
                    type: "select",
                    lookup:
                        "financial_categories",
                },
                item.financial_category_id
            );

            window.BuildinoSelect2
                ?.enhance(itemRow);

            $$([
                "input",
                "select",
            ].join(","), itemRow).forEach(
                (input) => {
                    input.addEventListener(
                        "input",
                        updatePreview
                    );
                    input.addEventListener(
                        "change",
                        updatePreview
                    );
                }
            );

            $(
                "[data-formula-remove-item]",
                itemRow
            ).addEventListener(
                "click",
                () => {
                    if (
                        $$(
                            "[data-formula-item]",
                            itemsContainer
                        ).length === 1
                    ) {
                        toast(
                            "حداقل یک ردیف هزینه باید باقی بماند.",
                            "danger"
                        );

                        return;
                    }

                    window.BuildinoSelect2
                        ?.destroy(itemRow);
                    itemRow.remove();
                    updatePreview();
                }
            );

            updatePreview();
        };

        const initialItems =
            row?.items?.length
                ? row.items
                : [
                    {
                        title: "شارژ پایه",
                        base_amount: 0,
                    },
                ];

        await Promise.all(
            initialItems.map(
                (item) => addItem(item)
            )
        );

        $(
            "[data-formula-add-item]",
            component
        ).addEventListener(
            "click",
            () => addItem({
                title: "",
                base_amount: 0,
            })
        );

        $$(
            "[data-formula-method]",
            component
        ).forEach((input) => {
            input.addEventListener(
                "change",
                updatePreview
            );
        });

        $(
            "[data-formula-example]",
            component
        ).addEventListener(
            "input",
            updatePreview
        );

        $(
            "[data-formula-advanced-toggle]",
            component
        ).addEventListener(
            "click",
            (event) => {
                const isAdvanced =
                    component.dataset
                        .builderMode
                    === "advanced";
                const nextMode =
                    isAdvanced
                        ? "guided"
                        : "advanced";
                const generated =
                    nextMode === "advanced"
                        ? collectChargeFormulaBuilder(
                            component
                        ).builder
                        : null;

                component.dataset.builderMode =
                    nextMode;
                $(
                    "[data-formula-guided]",
                    component
                ).hidden =
                    nextMode === "advanced";
                $(
                    "[data-formula-advanced]",
                    component
                ).hidden =
                    nextMode !== "advanced";
                event.currentTarget.textContent =
                    nextMode === "advanced"
                        ? "بازگشت به فرمول‌ساز ساده"
                        : "تنظیمات پیشرفته";

                if (nextMode === "advanced") {
                    advancedType.value =
                        generated
                            .calculation_type;
                    advancedConfiguration.value =
                        JSON.stringify(
                            {
                                generated_by:
                                    "guided_builder",
                                builder_version: 1,
                            },
                            null,
                            2
                        );
                    advancedItems.value =
                        JSON.stringify(
                            generated.items.map(
                                (item) => ({
                                    ...item,
                                    configuration: {},
                                })
                            ),
                            null,
                            2
                        );
                    window.BuildinoSelect2
                        ?.refresh(
                            advancedType
                        );
                }
            }
        );

        updatePreview();

        return component;
    };

    const createFieldElement = async (
        field,
        value = null,
        mode = "create",
        row = null
    ) => {
        if (
            mode === "edit"
            && field.create_only
        ) {
            return null;
        }

        if (
            field.type
            === "charge_formula_builder"
        ) {
            return createChargeFormulaBuilder(
                field,
                row
            );
        }

        const wrapper =
            document.createElement(
                "label"
            );

        wrapper.className =
            "crud-field";

        if (
            field.type === "textarea"
            || field.type === "json"
            || field.type === "multiselect"
        ) {
            wrapper.classList.add(
                "crud-field--wide"
            );
        }

        const required =
            field.required
            || (
                mode === "create"
                && field.required_on_create
            );

        const title =
            document.createElement(
                "span"
            );

        title.innerHTML =
            `${escapeHtml(field.label)}${required ? " <b>*</b>" : ""}`;

        wrapper.appendChild(
            title
        );

        let input;

        if (
            field.type === "textarea"
            || field.type === "json"
        ) {
            input =
                document.createElement(
                    "textarea"
                );

            input.rows =
                field.type === "json"
                    ? 8
                    : 5;
        } else if (
            field.type === "select"
            || field.type === "multiselect"
        ) {
            input =
                document.createElement(
                    "select"
                );

            if (
                field.type
                === "multiselect"
            ) {
                input.multiple =
                    true;
                input.size =
                    10;
            }

            if (
                field.options
            ) {
                if (
                    field.type
                    !== "multiselect"
                ) {
                    input.appendChild(
                        new Option(
                            "انتخاب کنید",
                            ""
                        )
                    );
                }

                field.options.forEach(
                    (option) => {
                        const node =
                            new Option(
                                option.label,
                                option.value
                            );

                        const values =
                            Array.isArray(
                                value
                            )
                                ? value.map(
                                    String
                                )
                                : [
                                    String(
                                        value
                                        ?? ""
                                    ),
                                ];

                        node.selected =
                            values.includes(
                                String(
                                    option.value
                                )
                            );

                        input.appendChild(
                            node
                        );
                    }
                );
            }
        } else if (
            field.type === "checkbox"
        ) {
            wrapper.classList.add(
                "crud-field--checkbox"
            );

            input =
                document.createElement(
                    "input"
                );

            input.type =
                "checkbox";
        } else {
            input =
                document.createElement(
                    "input"
                );

            if (
                [
                    "date",
                    "datetime-local",
                    "time",
                ].includes(
                    field.type
                )
            ) {
                input.type =
                    "text";
                input.dataset
                    .buildinoDateType =
                        field.type;
            } else {
                input.type =
                    field.type
                    || "text";
            }
        }

        input.name =
            field.name;

        input.dataset.fieldType =
            field.type
            || "text";

        const fieldName =
            String(
                field.name
                || ""
            ).toLowerCase();

        if (
            [
                "email",
                "password",
                "time",
                "date",
                "datetime-local",
                "number",
            ].includes(
                field.type
            )
            || /mobile|phone|email|code|iban|card|url|website/.test(
                fieldName
            )
        ) {
            input.dir =
                "ltr";
        }

        if (
            field.type
            === "password"
        ) {
            input.autocomplete =
                "new-password";
        }

        if (field.step) {
            input.step =
                field.step;
        }

        if (field.placeholder) {
            input.placeholder =
                field.placeholder;
        }

        if (required) {
            input.required =
                true;
        }

        if (
            mode === "edit"
            && field.readonly_on_edit
        ) {
            input.disabled =
                true;
        }

        if (
            field.type
            === "checkbox"
        ) {
            const checked =
                value !== null
                && value !== undefined
                    ? (
                        value === true
                        || value === 1
                        || value === "1"
                    )
                    : Boolean(
                        field.default
                    );

            input.checked =
                checked;

            const line =
                document.createElement(
                    "div"
                );

            line.className =
                "crud-checkbox-line";

            line.appendChild(
                input
            );

            const hint =
                document.createElement(
                    "small"
                );

            hint.textContent =
                "فعال";

            line.appendChild(
                hint
            );

            wrapper.appendChild(
                line
            );
        } else {
            if (
                field.type === "json"
            ) {
                if (
                    value !== null
                    && value !== undefined
                    && value !== ""
                ) {
                    input.value =
                        typeof value
                        === "string"
                            ? value
                            : JSON.stringify(
                                value,
                                null,
                                2
                            );
                } else if (
                    field.default
                    !== undefined
                ) {
                    input.value =
                        typeof field.default
                        === "string"
                            ? field.default
                            : JSON.stringify(
                                field.default,
                                null,
                                2
                            );
                }
            } else if (
                field.type
                !== "select"
                && field.type
                !== "multiselect"
            ) {
                input.value =
                    value
                    ?? field.default
                    ?? "";
            }

            wrapper.appendChild(
                input
            );
        }

        if (field.help) {
            const help =
                document.createElement(
                    "small"
                );

            help.className =
                "crud-field__help";
            help.textContent =
                field.help;

            wrapper.appendChild(
                help
            );
        }

        if (
            field.lookup
            && (
                field.type === "select"
                || field.type
                    === "multiselect"
            )
        ) {
            await loadLookupOptions(
                input,
                field,
                value
            );
        }

        return wrapper;
    };

    const renderFields = async (
        container,
        fields,
        row = null,
        mode = "create"
    ) => {
        window.BuildinoSelect2
            ?.destroy(container);

        container.innerHTML =
            "";

        for (const field of fields || []) {
            const value =
                row
                    ? getByPath(
                        row,
                        field.name
                    )
                    : null;

            const element =
                await createFieldElement(
                    field,
                    value,
                    mode,
                    row
                );

            if (element) {
                container.appendChild(
                    element
                );
            }
        }

        window.BuildinoSelect2
            ?.enhance(container);

        window.BuildinoJalaliDatepicker
            ?.enhance(container);
    };

    const collectPayload = (
        container,
        fields,
        mode = "create"
    ) => {
        const payload = {};

        for (const field of fields || []) {
            if (
                mode === "edit"
                && (
                    field.create_only
                    || field.readonly_on_edit
                )
            ) {
                continue;
            }

            if (
                field.type
                === "charge_formula_builder"
            ) {
                const component =
                    container.querySelector(
                        `[data-charge-formula-builder="${CSS.escape(field.name)}"]`
                    );

                if (component) {
                    Object.assign(
                        payload,
                        collectChargeFormulaBuilder(
                            component
                        )
                    );
                }

                continue;
            }

            const input =
                container.querySelector(
                    `[name="${CSS.escape(field.name)}"]`
                );

            if (! input) {
                continue;
            }

            let value = null;

            if (
                field.type
                === "checkbox"
            ) {
                value =
                    input.checked;
            } else if (
                field.type
                === "multiselect"
            ) {
                value =
                    [...input.selectedOptions]
                        .map(
                            (option) =>
                                Number(
                                    option.value
                                )
                        )
                        .filter(
                            Number.isFinite
                        );
            } else {
                value =
                    input.value;
            }

            if (
                field.type === "password"
                && ! value
                && mode === "edit"
            ) {
                continue;
            }

            if (
                field.type === "json"
            ) {
                const trimmed =
                    String(value || "")
                        .trim();

                if (! trimmed) {
                    continue;
                }

                try {
                    value =
                        JSON.parse(
                            trimmed
                        );
                } catch (error) {
                    throw new Error(
                        `مقدار «${field.label}» JSON معتبر نیست.`
                    );
                }
            } else if (
                field.type === "number"
            ) {
                if (
                    value === ""
                    || value === null
                ) {
                    value = null;
                } else {
                    value =
                        Number(value);

                    if (
                        Number.isNaN(
                            value
                        )
                    ) {
                        throw new Error(
                            `مقدار «${field.label}» عدد معتبر نیست.`
                        );
                    }
                }
            } else if (
                field.type !== "checkbox"
                && field.type
                    !== "multiselect"
            ) {
                if (
                    value === ""
                    && ! (
                        field.required
                        || (
                            mode
                            === "create"
                            && field
                                .required_on_create
                        )
                    )
                ) {
                    value =
                        null;
                }
            }

            setByPath(
                payload,
                field.name,
                value
            );
        }

        return payload;
    };

    const openDrawer = async (
        mode,
        row = null
    ) => {
        state.editRow =
            row;

        elements.recordId.value =
            row?.id ?? "";

        elements.drawerEyebrow.textContent =
            mode === "edit"
                ? "Edit"
                : "Create";

        elements.drawerTitle.textContent =
            mode === "edit"
                ? `ویرایش ${resource.title}`
                : `ثبت ${resource.title}`;

        elements.saveButton.textContent =
            mode === "edit"
                ? "ذخیره تغییرات"
                : "ثبت رکورد";

        elements.form.dataset.mode =
            mode;

        elements.formError.textContent =
            "";

        await renderFields(
            elements.formFields,
            resource.fields || [],
            row,
            mode
        );

        elements.drawer.classList.add(
            "is-open"
        );

        elements.drawerBackdrop
            .classList.add(
                "is-open"
            );

        elements.drawer.setAttribute(
            "aria-hidden",
            "false"
        );

        document.body.classList.add(
            "crud-overlay-open"
        );
    };

    const closeDrawer = () => {
        elements.drawer
            ?.classList
            .remove("is-open");

        elements.drawerBackdrop
            ?.classList
            .remove("is-open");

        elements.drawer
            ?.setAttribute(
                "aria-hidden",
                "true"
            );

        state.editRow =
            null;

        document.body.classList.remove(
            "crud-overlay-open"
        );
    };

    const saveForm = async (
        event
    ) => {
        event.preventDefault();

        const mode =
            elements.form
                .dataset
                .mode
            || "create";

        const endpointConfig =
            mode === "edit"
                ? resource.update
                : resource.create;

        if (! endpointConfig) {
            return;
        }

        try {
            const payload =
                collectPayload(
                    elements.formFields,
                    resource.fields || [],
                    mode
                );

            const endpoint =
                interpolate(
                    endpointConfig.url,
                    state.editRow
                );

            elements.saveButton.disabled =
                true;
            elements.saveButton.textContent =
                "در حال ذخیره...";

            await apiFetch(
                endpoint,
                {
                    method:
                        endpointConfig.method
                        || (
                            mode === "edit"
                                ? "PATCH"
                                : "POST"
                        ),
                    body:
                        JSON.stringify(
                            payload
                        ),
                }
            );

            toast(
                mode === "edit"
                    ? "تغییرات با موفقیت ذخیره شد."
                    : "رکورد با موفقیت ثبت شد."
            );

            closeDrawer();

            if (resource.list) {
                await loadRows();
            }
        } catch (error) {
            elements.formError
                .textContent =
                error.message;
        } finally {
            elements.saveButton.disabled =
                false;
            elements.saveButton.textContent =
                mode === "edit"
                    ? "ذخیره تغییرات"
                    : "ثبت رکورد";
        }
    };

    const deleteRow = async (
        row
    ) => {
        if (! resource.delete) {
            return;
        }

        if (
            ! await confirmAction(
                "رکورد انتخاب‌شده حذف شود؟ این عملیات ممکن است قابل بازگشت نباشد.",
                {
                    confirmText:
                        "حذف رکورد",
                    tone:
                        "danger",
                }
            )
        ) {
            return;
        }

        try {
            const endpoint =
                interpolate(
                    resource.delete.url,
                    row
                );

            await apiFetch(
                endpoint,
                {
                    method:
                        resource.delete
                            .method
                        || "DELETE",
                }
            );

            toast(
                "رکورد حذف شد."
            );

            await loadRows();
        } catch (error) {
            toast(
                error.message,
                "danger"
            );
        }
    };

    const openAction = async (
        action,
        row
    ) => {
        if (action.open_url) {
            try {
                const endpoint = interpolate(
                    action.url,
                    row
                );

                window.open(
                    endpoint,
                    "_blank",
                    "noopener,noreferrer"
                );
            } catch (error) {
                toast(error.message, "danger");
            }

            return;
        }

        if (
            action.confirm
            && ! (action.fields || []).length
        ) {
            if (
                ! await confirmAction(
                    action.confirm,
                    {
                        confirmText:
                            action.title
                            || "اجرا",
                        tone:
                            action.tone
                            || "warning",
                    }
                )
            ) {
                return;
            }

            await executeAction(
                action,
                row,
                {}
            );
            return;
        }

        if (
            ! (action.fields || []).length
        ) {
            await executeAction(
                action,
                row,
                {}
            );
            return;
        }

        state.action =
            action;
        state.actionRow =
            row;

        elements.actionTitle
            .textContent =
            action.title;

        elements.actionError
            .textContent =
            "";

        elements.actionSubmit
            .className =
            `crud-button crud-button--${action.tone || "primary"}`;

        await renderFields(
            elements.actionFields,
            action.fields || [],
            row,
            "action"
        );

        elements.actionModal
            .classList
            .add("is-open");

        elements.actionBackdrop
            .classList
            .add("is-open");

        elements.actionModal
            .setAttribute(
                "aria-hidden",
                "false"
            );

        document.body.classList.add(
            "crud-overlay-open"
        );
    };

    const closeAction = () => {
        elements.actionModal
            ?.classList
            .remove("is-open");

        elements.actionBackdrop
            ?.classList
            .remove("is-open");

        elements.actionModal
            ?.setAttribute(
                "aria-hidden",
                "true"
            );

        state.action =
            null;
        state.actionRow =
            null;

        document.body.classList.remove(
            "crud-overlay-open"
        );
    };

    const executeAction = async (
        action,
        row,
        payload
    ) => {
        if (
            action.confirm
            && (action.fields || []).length
            && ! await confirmAction(
                action.confirm,
                {
                    confirmText:
                        action.title
                        || "اجرا",
                    tone:
                        action.tone
                        || "warning",
                }
            )
        ) {
            return;
        }

        try {
            const endpoint =
                interpolate(
                    action.url,
                    row
                );

            await apiFetch(
                endpoint,
                {
                    method:
                        action.method
                        || "POST",
                    body:
                        ["GET", "DELETE"]
                            .includes(
                                String(
                                    action.method
                                    || "POST"
                                ).toUpperCase()
                            )
                            ? undefined
                            : JSON.stringify(
                                payload
                            ),
                }
            );

            toast(
                `عملیات «${action.title}» با موفقیت انجام شد.`
            );

            closeAction();

            if (resource.list) {
                await loadRows();
            }
        } catch (error) {
            if (
                elements.actionModal
                    ?.classList
                    .contains("is-open")
            ) {
                elements.actionError
                    .textContent =
                    error.message;
            } else {
                toast(
                    error.message,
                    "danger"
                );
            }
        }
    };

    const submitAction = async (
        event
    ) => {
        event.preventDefault();

        if (
            ! state.action
            || ! state.actionRow
        ) {
            return;
        }

        try {
            const payload =
                collectPayload(
                    elements.actionFields,
                    state.action.fields
                    || [],
                    "action"
                );

            elements.actionSubmit.disabled =
                true;
            elements.actionSubmit.textContent =
                "در حال اجرا...";

            await executeAction(
                state.action,
                state.actionRow,
                payload
            );
        } catch (error) {
            elements.actionError
                .textContent =
                error.message;
        } finally {
            elements.actionSubmit.disabled =
                false;
            elements.actionSubmit.textContent =
                "اجرا";
        }
    };

    const bindTableActions = () => {
        $$(
            "[data-row-action='edit']",
            elements.tableBody
        ).forEach((button) => {
            button.addEventListener(
                "click",
                () => {
                    const row =
                        findRow(
                            button.dataset.id
                        );

                    if (row) {
                        openDrawer(
                            "edit",
                            row
                        );
                    }
                }
            );
        });

        $$(
            "[data-row-action='delete']",
            elements.tableBody
        ).forEach((button) => {
            button.addEventListener(
                "click",
                () => {
                    const row =
                        findRow(
                            button.dataset.id
                        );

                    if (row) {
                        deleteRow(row);
                    }
                }
            );
        });

        $$(
            "[data-special-action]",
            elements.tableBody
        ).forEach((button) => {
            button.addEventListener(
                "click",
                () => {
                    const row =
                        findRow(
                            button.dataset.id
                        );

                    const action =
                        (
                            resource.actions
                            || []
                        ).find(
                            (item) =>
                                item.key
                                === button
                                    .dataset
                                    .specialAction
                        );

                    if (
                        row
                        && action
                    ) {
                        openAction(
                            action,
                            row
                        );
                    }
                }
            );
        });
    };

    const loadSingleton = async () => {
        if (! resource.show) {
            await renderFields(
                elements.singletonFields,
                resource.fields || [],
                null,
                "create"
            );
            return;
        }

        try {
            const validation =
                validateRequiredContext(
                    resource.show.url
                );

            if (! validation.valid) {
                toast(
                    validation.message,
                    "warning"
                );
                return;
            }

            const endpoint =
                interpolate(
                    resource.show.url
                );

            const { payload } =
                await apiFetch(
                    endpoint,
                    {
                        method:
                            resource.show
                                .method
                            || "GET",
                    }
                );

            let data =
                payload?.data
                ?? payload
                ?? null;

            if (
                resource.singleton_wrap
                && Array.isArray(data)
            ) {
                data = {
                    [resource.singleton_wrap]:
                        data,
                };
            }

            await renderFields(
                elements.singletonFields,
                resource.fields || [],
                data,
                "create"
            );

            toast(
                "مقادیر فعلی بارگذاری شد.",
                "info"
            );
        } catch (error) {
            toast(
                error.message,
                "danger"
            );
        }
    };

    const saveSingleton = async (
        event
    ) => {
        event.preventDefault();

        if (! resource.create) {
            return;
        }

        try {
            const payload =
                collectPayload(
                    elements.singletonFields,
                    resource.fields || [],
                    "create"
                );

            const endpoint =
                interpolate(
                    resource.create.url
                );

            await apiFetch(
                endpoint,
                {
                    method:
                        resource.create
                            .method
                        || "PUT",
                    body:
                        JSON.stringify(
                            payload
                        ),
                }
            );

            toast(
                "تنظیمات با موفقیت ذخیره شد."
            );

            await loadSingleton();
        } catch (error) {
            toast(
                error.message,
                "danger"
            );
        }
    };

    const initSingleton = async () => {
        if (
            resource.mode
            !== "singleton"
            || ! elements
                .singletonFields
        ) {
            return;
        }

        await renderFields(
            elements.singletonFields,
            resource.fields || [],
            null,
            "create"
        );

        elements.loadSingleton
            ?.addEventListener(
                "click",
                loadSingleton
            );

        elements.singletonForm
            ?.addEventListener(
                "submit",
                saveSingleton
            );

        const required =
            validateRequiredContext();

        if (required.valid) {
            await loadSingleton();
        }
    };

    const initEvents = () => {
        elements.createButton
            ?.addEventListener(
                "click",
                () => {
                    const validation =
                        validateRequiredContext(
                            resource
                                .create
                                ?.url
                        );

                    if (
                        ! validation.valid
                    ) {
                        toast(
                            validation.message,
                            "warning"
                        );
                        return;
                    }

                    openDrawer(
                        "create"
                    );
                }
            );

        elements.refreshButton
            ?.addEventListener(
                "click",
                loadRows
            );

        elements.search
            ?.addEventListener(
                "input",
                () => {
                    state.currentPage =
                        1;
                    applySearch();
                }
            );

        elements.drawerClose
            ?.addEventListener(
                "click",
                closeDrawer
            );

        elements.cancelButton
            ?.addEventListener(
                "click",
                closeDrawer
            );

        elements.drawerBackdrop
            ?.addEventListener(
                "click",
                closeDrawer
            );

        elements.form
            ?.addEventListener(
                "submit",
                saveForm
            );

        elements.actionClose
            ?.addEventListener(
                "click",
                closeAction
            );

        elements.actionCancel
            ?.addEventListener(
                "click",
                closeAction
            );

        elements.actionBackdrop
            ?.addEventListener(
                "click",
                closeAction
            );

        elements.actionForm
            ?.addEventListener(
                "submit",
                submitAction
            );

        document.addEventListener(
            "keydown",
            (event) => {
                if (
                    event.key
                    === "Escape"
                ) {
                    closeDrawer();
                    closeAction();
                }
            }
        );
    };

    const init = async () => {
        initEvents();

        await initContextLookups();

        if (
            resource.mode
            === "singleton"
        ) {
            await initSingleton();
            return;
        }

        if (resource.list) {
            await loadRows();
        } else {
            showState(
                "این صفحه فقط برای اجرای عملیات ثبت طراحی شده است."
            );
        }

        const url =
            new URL(
                window.location.href
            );

        if (
            url.searchParams.get(
                "create"
            ) === "1"
            && resource.create
        ) {
            const validation =
                validateRequiredContext(
                    resource.create.url
                );

            if (
                validation.valid
            ) {
                await openDrawer(
                    "create"
                );

                url.searchParams.delete(
                    "create"
                );

                window.history
                    .replaceState(
                        {},
                        "",
                        url
                    );
            }
        }
    };

    init().catch((error) => {
        showState(
            error.message,
            "danger"
        );
    });
})();
