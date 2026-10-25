
(() => {
    "use strict";

    const bootstrap = window.BuildinoCrud;

    if (! bootstrap) {
        return;
    }

    const resource = bootstrap.resource || {};
    const core = window.BuildinoCoreFlows;
    const coreResource = ["blocks", "floors", "units", "users", "roles", "role-assignments", "invitations"].includes(bootstrap.resourceKey);
    if (coreResource && core && resource.create && !core.allowed(bootstrap.resourceKey, "create")) delete resource.create;
    if (coreResource && core && resource.update && !core.allowed(bootstrap.resourceKey, "update")) delete resource.update;
    const app = document.getElementById("buildinoCrudApp");

    if (! app) {
        return;
    }
    if (coreResource) app.dataset.coreProcessUi = '1';

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

    // Escape transformed/overflow-clipped layout ancestors on the users page.
    // Keep the same nodes so form listeners and focus restoration still work.
    if (bootstrap.resourceKey === "users") {
        document.body.append(elements.drawerBackdrop, elements.drawer);
        elements.drawerBackdrop.classList.add("user-registration-backdrop");
        resource.fields = (resource.fields || []).filter(
            (field) => !["password", "password_confirmation"].includes(field.name)
        );
    }

    const inlineEditor = elements.drawer?.hasAttribute('data-inline-editor');
    const invitationUi = bootstrap.resourceKey === 'invitations' ? window.BuildinoInvitationUi : null;
    const scopedFormula = bootstrap.resourceKey === 'charge-formulas' && Array.isArray(bootstrap.formulaBuildings);
    const formulaPermission = action => !scopedFormula || Boolean(bootstrap.formulaBuildings.find(row => String(row.id) === $("[data-context-name='building_id']")?.value)?.[action === 'create' ? 'canCreate' : 'canUpdate']);
    const editorVisibility = open => {
        if (!inlineEditor) return;
        elements.drawer.hidden = !open;
        app.dispatchEvent(new CustomEvent('buildino:inline-editor', { detail: { open } }));
    };

    const state = {
        rows: [],
        filteredRows: [],
        currentPage: 1,
        pageSize: 25,
        remotePage: 1,
        remoteLastPage: 1,
        remoteEndpoint: null,
        editRow: null,
        action: null,
        actionRow: null,
        loading: false,
        drawerRenderId: 0,
        drawerTrigger: null,
        listRenderId: 0,
    };

    const lookupCache = new Map();

    const nextPaint = () =>
        new Promise((resolve) => {
            requestAnimationFrame(() => {
                requestAnimationFrame(resolve);
            });
        });

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
        const processLabels = bootstrap.resourceKey === 'invitations' ? {
            status: { pending: 'در انتظار ارسال', sent: 'ارسال‌شده', accepted: 'پذیرفته‌شده', cancelled: 'لغوشده', expired: 'منقضی‌شده' },
            relation_type: { owner: 'مالک', tenant: 'مستأجر', resident: 'ساکن', family_member: 'عضو خانواده' },
            channel: { sms: 'پیامک', email: 'ایمیل' },
        } : bootstrap.resourceKey === 'units' ? {
            usage_type: { residential: 'مسکونی', commercial: 'تجاری', office: 'اداری', other: 'سایر' },
        } : bootstrap.resourceKey === 'charge-formulas' ? {
            calculation_type: { fixed: 'مبلغ ثابت هر واحد', area: 'متناسب با متراژ', persons: 'متناسب با نفرات', equal: 'تقسیم مساوی', floor_coefficient: 'ضریب طبقه' },
        } : {};
        const displayValue = processLabels[column.key]?.[value] ?? value;

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
                    ${bootstrap.resourceKey === 'charge-formulas' && column.key === 'is_active' ? (boolValue ? 'فعال' : 'غیرفعال') : (boolValue ? "بله" : "خیر")}
                </span>
            `;
        }

        if (
            column.key === "status"
            || column.key.endsWith("_status")
        ) {
            return `
                <span class="crud-status crud-status--${statusTone(value)}">
                    ${escapeHtml(formatValue(displayValue))}
                </span>
            `;
        }

        let formatted = formatValue(displayValue);

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
        if (bootstrap.resourceKey === 'users') {
            return rows.map((row) => ({
                ...row,
                full_name: row.full_name || [row.first_name, row.last_name].filter(Boolean).join(' '),
                roles: row.roles || (row.user_role_assignments || []).filter((assignment) => assignment.is_active).map((assignment) => assignment.role?.display_name || assignment.role?.name).filter(Boolean),
            }));
        }
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

    const lookupRows = (url) => {
        if (lookupCache.has(url)) {
            return lookupCache.get(url);
        }

        const request = apiFetch(url)
            .then(({ payload }) =>
                rowsFromPayload(payload)
            )
            .catch((error) => {
                lookupCache.delete(url);
                throw error;
            });

        lookupCache.set(url, request);

        return request;
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

        const requestId = String(
            Number(
                select.dataset.lookupRequestId
                || 0
            ) + 1
        );

        if (
            select.dataset.lookupOriginalDisabled
            === undefined
        ) {
            select.dataset.lookupOriginalDisabled =
                String(select.disabled);
        }

        const wasDisabled =
            select.dataset.lookupOriginalDisabled
            === "true";

        select.dataset.lookupRequestId =
            requestId;
        select.disabled = true;

        select.innerHTML =
            placeholder
            + `<option value="" disabled>در حال بارگذاری...</option>`;

        refreshLookupSelect(
            select
        );

        try {
            const rows = await lookupRows(
                lookupUrl(
                    field.lookup,
                    field
                )
            );

            if (
                select.dataset.lookupRequestId
                !== requestId
            ) {
                return;
            }

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

            if (
                field.auto_select_single
                && rows.length === 1
                && (
                    selectedValue === null
                    || selectedValue === ""
                    || (
                        Array.isArray(selectedValue)
                        && selectedValue.length === 0
                    )
                )
            ) {
                select.value = String(
                    rows[0].id
                );
            }

            refreshLookupSelect(
                select
            );
        } catch (error) {
            if (
                select.dataset.lookupRequestId
                !== requestId
            ) {
                return;
            }

            select.innerHTML =
                placeholder
                + `<option value="" disabled>خطا در دریافت گزینه‌ها</option>`;

            refreshLookupSelect(
                select
            );
        } finally {
            if (
                select.dataset.lookupRequestId
                === requestId
            ) {
                select.disabled =
                    wasDisabled;
                delete select.dataset
                    .lookupOriginalDisabled;

                refreshLookupSelect(
                    select
                );
            }
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
                    state.remoteEndpoint = null;
                    if (coreResource) core?.contextChanged(bootstrap.resourceKey, select.value);
                    lookupCache.clear();

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
        const renderId = ++state.listRenderId;
        if (! resource.list) {
            showState(
                "برای این صفحه فهرست جداگانه تعریف نشده است."
            );
            return;
        }

        let endpoint = null;

        try {
            endpoint =
                state.remoteEndpoint || listEndpoint();
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

            if ((scopedFormula || invitationUi) && renderId !== state.listRenderId) return;
            state.rows =
                transformRows(
                    rowsFromPayload(
                        payload
                    )
                );

            state.currentPage = 1;

            const pagination = payload?.meta || payload || {};
            state.remotePage = pagination.current_page || 1;
            state.remoteLastPage = pagination.last_page || 1;
            if (coreResource) core?.setRows(state.rows);

            applySearch();
            setLoading(false);
            renderRemotePagination();

            if (! state.rows.length) {
                showState(
                    coreResource ? `هنوز ${core?.labels[bootstrap.resourceKey] || "موردی"} ثبت نشده است؛ از دکمه افزودن شروع کنید.` : "رکوردی برای نمایش وجود ندارد."
                );
            }
        } catch (error) {
            if ((scopedFormula || invitationUi) && renderId !== state.listRenderId) return;
            if (scopedFormula || invitationUi) setLoading(false);
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
                        `<th scope="col">${escapeHtml(column.label)}</th>`
                )
                .join("")
            + `<th scope="col" class="crud-actions-column">عملیات</th>`;

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

            if (resource.update && formulaPermission('update')) {
                actions.push(`
                    <button
                        type="button"
                        class="crud-row-action crud-row-action--edit"
                        data-row-action="edit"
                        data-id="${escapeHtml(row.id)}"
                        aria-label="ویرایش ${escapeHtml(row.title || resource.title)}"
                    >
                        ویرایش
                    </button>
                `);
            }

            (resource.actions || []).filter((action) => bootstrap.resourceKey !== "invitations" || (
                core?.allowed("invitations", "update") && (
                    action.key === "resend" ? ["pending", "sent"].includes(row.status) && (!row.expires_at || Date.parse(row.expires_at) > Date.now()) : !["accepted", "cancelled", "expired"].includes(row.status)
                )
            ))
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
            value: "area",
            title: "بر اساس متراژ",
            description:
                "مبلغ هزینه دوره متناسب با متراژ واحدها تقسیم می‌شود.",
        },
        {
            value: "persons",
            title: "بر اساس تعداد نفرات",
            description:
                "مبلغ هزینه دوره متناسب با تعداد ساکنان واحدها تقسیم می‌شود.",
        },
        {
            value: "equal",
            title: "تقسیم مساوی بین واحدها",
            description:
                "مبلغ هزینه دوره به تعداد واحدهای مشمول تقسیم می‌شود.",
        },
        {
            value: "floor_coefficient",
            title: "بر اساس ضریب طبقه",
            description:
                "مبلغ هزینه دوره بر اساس ضریب تعریف‌شده برای طبقه هر واحد تقسیم می‌شود.",
        },
        {
            value: "fixed",
            title: "مبلغ ثابت برای هر واحد",
            description:
                "برای هر واحد در هر دوره، مبلغ ثابت تعریف‌شده بدهکار می‌شود.",
        },
    ];

    const formulaNumber = (value) =>
        new Intl.NumberFormat("fa-IR")
            .format(Number(value) || 0);

    const parseFloorCoefficients = (
        rawValue
    ) => {
        const raw = String(rawValue || "")
            .trim();

        if (! raw) {
            return {};
        }

        if (raw.startsWith("{")) {
            const value = JSON.parse(raw);

            if (
                ! value
                || Array.isArray(value)
                || typeof value !== "object"
            ) {
                throw new Error(
                    "ضرایب طبقات باید یک شیء معتبر باشند."
                );
            }

            return Object.fromEntries(
                Object.entries(value).map(
                    ([floor, coefficient]) => {
                        const number =
                            Number(coefficient);

                        if (
                            ! Number.isFinite(number)
                            || number <= 0
                        ) {
                            throw new Error(
                                `ضریب طبقه «${floor}» معتبر نیست.`
                            );
                        }

                        return [
                            String(floor).trim(),
                            number,
                        ];
                    }
                )
            );
        }

        const coefficients = {};

        raw
            .split(/[\n,]+/)
            .map((line) => line.trim())
            .filter(Boolean)
            .forEach((line) => {
                const match = line.match(
                    /^(-?\d+)\s*[:=]\s*(\d+(?:\.\d+)?)$/
                );

                if (! match) {
                    throw new Error(
                        "ضرایب طبقات را به شکل «1=1، 2=1.2» وارد کنید."
                    );
                }

                const coefficient =
                    Number(match[2]);

                if (
                    ! Number.isFinite(
                        coefficient
                    )
                    || coefficient <= 0
                ) {
                    throw new Error(
                        `ضریب طبقه «${match[1]}» معتبر نیست.`
                    );
                }

                coefficients[match[1]] =
                    coefficient;
            });

        return coefficients;
    };

    const collectChargeFormulaBuilder = (
        component
    ) => {
        const calculationType = $(
            "[data-formula-method]:checked",
            component
        )?.value;
        const categoryValue = $(
            "[data-formula-category]",
            component
        )?.value;

        if (! calculationType) {
            throw new Error(
                "روش محاسبه شارژ را انتخاب کنید."
            );
        }

        if (! categoryValue) {
            throw new Error(
                "دسته هزینه را انتخاب کنید."
            );
        }

        const builder = {
            calculation_type:
                calculationType,
            financial_category_id:
                Number(categoryValue),
        };

        if (
            calculationType === "fixed"
        ) {
            const rawAmount = $(
                "[data-formula-fixed-amount]",
                component
            )?.value;
            const amount =
                Number(rawAmount);

            if (
                rawAmount === ""
                || ! Number.isSafeInteger(
                    amount
                )
                || amount < 0
            ) {
                throw new Error(
                    "مبلغ ثابت هر واحد را به‌صورت عدد صحیح وارد کنید."
                );
            }

            builder.fixed_amount =
                amount;
        }

        if (
            calculationType
            === "floor_coefficient"
        ) {
            const coefficients =
                parseFloorCoefficients(
                    $(
                        "[data-formula-floor-coefficients]",
                        component
                    )?.value
                );

            if (
                ! Object.keys(
                    coefficients
                ).length
            ) {
                throw new Error(
                    "حداقل یک ضریب طبقه تعریف کنید."
                );
            }

            builder.floor_coefficients =
                coefficients;
        }

        return {
            builder,
        };
    };

    const createChargeFormulaBuilder = async (
        field,
        row = null
    ) => {
        formulaBuilderSequence += 1;

        const component =
            document.createElement(
                "section"
            );
        const methodName =
            `charge-formula-method-${formulaBuilderSequence}`;
        const currentType =
            String(
                row?.calculation_type
                || "area"
            );
        const guidedType =
            formulaMethods.some(
                (method) =>
                    method.value
                    === currentType
            )
                ? currentType
                : "area";

        const item =
            row?.items?.[0]
            || null;

        const floorCoefficients =
            row?.configuration
                ?.floor_coefficients
            || {};

        component.className =
            "crud-field crud-field--wide crud-formula-builder";
        component.dataset
            .chargeFormulaBuilder =
                field.name;

        component.innerHTML = `
            <div class="crud-formula-builder__heading">
                <div>
                    <strong>${escapeHtml(field.label)}</strong>
                    <p>
                        این قاعده یک‌بار برای ساختمان تعریف می‌شود و در تمام دوره‌های شارژ روی هزینه همان دسته اعمال می‌شود.
                    </p>
                </div>
            </div>

            <label class="crud-field">
                <span>دسته هزینه <b>*</b></span>
                <select
                    data-formula-category
                    required
                ></select>
            </label>

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

            <label
                class="crud-field"
                data-formula-fixed-wrap
            >
                <span>مبلغ ثابت هر واحد (ریال)</span>
                <input
                    type="number"
                    min="0"
                    step="1"
                    dir="ltr"
                    value="${Number(item?.base_amount || 0)}"
                    data-formula-fixed-amount
                >
            </label>

            <label
                class="crud-field crud-field--wide"
                data-formula-floor-wrap
            >
                <span>ضرایب طبقات</span>
                <textarea
                    rows="5"
                    dir="ltr"
                    placeholder="1=1&#10;2=1.2&#10;3=1.4"
                    data-formula-floor-coefficients
                >${escapeHtml(
                    Object.entries(
                        floorCoefficients
                    )
                        .map(
                            ([floor, coefficient]) =>
                                `${floor}=${coefficient}`
                        )
                        .join("\n")
                )}</textarea>
                <small>
                    هر خط: شماره طبقه = ضریب. برای طبقه همکف می‌توانید 0 و برای زیرزمین عدد منفی وارد کنید.
                </small>
            </label>
        `;

        const categorySelect = $(
            "[data-formula-category]",
            component
        );

        await loadLookupOptions(
            categorySelect,
            {
                type: "select",
                lookup:
                    "expense_categories",
            },
            item?.financial_category_id
                ?? null
        );

        const syncVisibility = () => {
            const type = $(
                "[data-formula-method]:checked",
                component
            )?.value;

            $(
                "[data-formula-fixed-wrap]",
                component
            ).hidden =
                type !== "fixed";

            $(
                "[data-formula-floor-wrap]",
                component
            ).hidden =
                type
                !== "floor_coefficient";
        };

        $$(
            "[data-formula-method]",
            component
        ).forEach((input) => {
            input.addEventListener(
                "change",
                syncVisibility
            );
        });

        syncVisibility();

        if (window.BuildinoChargeFormulaUi) {
            window.BuildinoChargeFormulaUi.mount(component, {
                collect: () => collectChargeFormulaBuilder(component),
                form: elements.form,
                saveButton: elements.saveButton,
                row,
                reloadCategories: async () => {
                    lookupCache.clear();
                    await loadLookupOptions(categorySelect, { type: "select", lookup: "expense_categories" }, categorySelect.value || item?.financial_category_id || null);
                },
            });
        }

        window.BuildinoSelect2
            ?.enhance(component);

        return component;
    };

    const createFieldElement = async (
        field,
        value = null,
        mode = "create",
        row = null,
        loadLookup = true
    ) => {
        if (
            (
                mode === "edit"
                && field.create_only
            )
            || (
                mode === "create"
                && field.edit_only
            )
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

                        const selectedValue =
                            value
                            ?? field.default
                            ?? "";

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
            || /mobile|phone|email|code|iban|card|url|website|postal/.test(
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
        } else if (/first_name/.test(fieldName)) {
            input.autocomplete =
                "given-name";
        } else if (/last_name/.test(fieldName)) {
            input.autocomplete =
                "family-name";
        } else if (/email/.test(fieldName)) {
            input.autocomplete =
                "email";
            input.inputMode =
                "email";
        } else if (/mobile|phone/.test(fieldName)) {
            input.autocomplete =
                "tel";
            input.inputMode =
                "tel";
        } else if (/postal/.test(fieldName)) {
            input.autocomplete =
                "postal-code";
            input.inputMode =
                "numeric";
        } else if (/iban|card|code/.test(fieldName)) {
            input.inputMode =
                "numeric";
        } else if (/url|website/.test(fieldName)) {
            input.autocomplete =
                "url";
            input.inputMode =
                "url";
        } else if (field.type === "number") {
            input.inputMode =
                field.step
                && String(field.step) !== "1"
                    ? "decimal"
                    : "numeric";
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
            input.setAttribute(
                "aria-required",
                "true"
            );
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
            loadLookup
            &&
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

        const renderedFields = [];

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
                    row,
                    false
                );

            if (element) {
                container.appendChild(
                    element
                );

                renderedFields.push({
                    field,
                    value,
                    input: element.querySelector(
                        `[name="${CSS.escape(field.name)}"]`
                    ),
                });
            }
        }

        window.BuildinoJalaliDatepicker
            ?.enhance(container);

        const lookupFields = new Map(
            renderedFields
                .filter(({ field, input }) =>
                    Boolean(
                        input
                        && field.lookup
                        && (
                            field.type === "select"
                            || field.type === "multiselect"
                        )
                    )
                )
                .map((item) => [
                    item.field.name,
                    item,
                ])
        );

        const lookupTasks = new Map();

        const loadFieldLookup = (item) => {
            if (! item) {
                return Promise.resolve();
            }

            if (
                lookupTasks.has(
                    item.field.name
                )
            ) {
                return lookupTasks.get(
                    item.field.name
                );
            }

            const dependency =
                lookupFields.get(
                    item.field.depends_on
                );

            const task = (dependency
                ? loadFieldLookup(dependency)
                : Promise.resolve()
            ).then(() =>
                loadLookupOptions(
                    item.input,
                    item.field,
                    item.value
                )
            );

            lookupTasks.set(
                item.field.name,
                task
            );

            return task;
        };

        await Promise.all(
            [...lookupFields.values()]
                .map(loadFieldLookup)
        );

        window.BuildinoSelect2
            ?.enhance(container);

        const dependentsByField =
            new Map();

        lookupFields.forEach((item) => {
            if (! item.field.depends_on) {
                return;
            }

            const dependents =
                dependentsByField.get(
                    item.field.depends_on
                ) || [];

            dependents.push(item);
            dependentsByField.set(
                item.field.depends_on,
                dependents
            );
        });

        const reloadDependents = async (
            fieldName
        ) => {
            const dependents =
                dependentsByField.get(
                    fieldName
                ) || [];

            await Promise.all(
                dependents.map(
                    async (item) => {
                        await loadLookupOptions(
                            item.input,
                            item.field,
                            null
                        );

                        await reloadDependents(
                            item.field.name
                        );
                    }
                )
            );
        };

        renderedFields.forEach(({ field, input }) => {
            if (
                ! input
                || ! dependentsByField.has(
                    field.name
                )
            ) {
                return;
            }

            input.addEventListener(
                "change",
                () => {
                    reloadDependents(
                        field.name
                    );
                }
            );
        });

        container.dispatchEvent(
            new CustomEvent(
                "buildino:form-mounted",
                {
                    bubbles: true,
                }
            )
        );
    };

    const collectPayload = (
        container,
        fields,
        mode = "create"
    ) => {
        const payload = {};

        for (const field of fields || []) {
            if (
                (
                    mode === "edit"
                    && (
                        field.create_only
                        || field.readonly_on_edit
                    )
                )
                || (
                    mode === "create"
                    && field.edit_only
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

    const renderDrawerSkeleton = () => {
        window.BuildinoSelect2
            ?.destroy(
                elements.formFields
            );

        elements.formFields.innerHTML =
            Array.from(
                { length: 6 },
                (_, index) => `
                    <div
                        class="crud-field-skeleton${index > 3 ? " crud-field-skeleton--wide" : ""}"
                        aria-hidden="true"
                    >
                        <span></span>
                        <i></i>
                    </div>
                `
            ).join("");
    };

    const setDrawerLoading = (
        loading
    ) => {
        elements.drawer.classList.toggle(
            "is-loading",
            loading
        );
        elements.formFields.setAttribute(
            "aria-busy",
            String(loading)
        );
        elements.saveButton.disabled =
            loading;
        if (!loading) $("[data-charge-formula-builder]", elements.formFields)?.syncFormulaFooter?.();
        if (!loading) invitationUi?.sync();
    };

    const lockPageScroll = () => {
        const scrollbarWidth =
            window.innerWidth
            - document.documentElement
                .clientWidth;

        document.body.style.setProperty(
            "--crud-scrollbar-compensation",
            `${Math.max(0, scrollbarWidth)}px`
        );
        document.body.classList.add(
            "crud-overlay-open"
        );
    };

    const unlockPageScroll = () => {
        document.body.classList.remove(
            "crud-overlay-open"
        );
        document.body.style.removeProperty(
            "--crud-scrollbar-compensation"
        );
    };

    const openDrawer = async (
        mode,
        row = null
    ) => {
        if (!formulaPermission(mode === 'edit' ? 'update' : 'create')) return;
        if (invitationUi?.busy) return;
        invitationUi?.destroy();
        if (invitationUi) lookupCache.clear();
        const renderId =
            ++state.drawerRenderId;

        state.drawerTrigger =
            document.activeElement
                instanceof HTMLElement
                ? document.activeElement
                : null;

        state.editRow =
            row;

        elements.recordId.value =
            row?.id ?? "";

        elements.drawerEyebrow.textContent =
            mode === "edit"
                ? "ویرایش رکورد"
                : "ثبت رکورد";

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

        renderDrawerSkeleton();
        setDrawerLoading(true);

        elements.drawer.classList.add(
            "is-open"
        );
        editorVisibility(true);

        if (!inlineEditor) elements.drawerBackdrop
            .classList.add(
                "is-open"
            );

        elements.drawer.setAttribute(
            "aria-hidden",
            "false"
        );

        if (!inlineEditor) lockPageScroll();

        elements.drawerClose.focus({
            preventScroll: true,
        });

        if (!inlineEditor) await nextPaint();

        try {
            await renderFields(
                elements.formFields,
                resource.fields || [],
                row,
                mode
            );
            if (coreResource) await core?.enhanceForm(elements.formFields, mode);

            if (
                renderId
                !== state.drawerRenderId
                || ! elements.drawer
                    .classList
                    .contains("is-open")
            ) {
                return;
            }

            const firstField =
                elements.formFields
                    .querySelector(
                        "input:not([disabled]), select:not([disabled]), textarea:not([disabled])"
                    );

            invitationUi?.mountForm({ form: elements.form, fields: elements.formFields, saveButton: elements.saveButton, retry: () => openDrawer('create') });

            const focusTarget = inlineEditor ? $(".charge-wizard__title, .invitation-step-title", elements.drawer) || firstField : firstField;
            focusTarget?.focus({
                preventScroll: true,
            });
        } catch (error) {
            if (
                renderId
                === state.drawerRenderId
            ) {
                elements.formError.textContent =
                    error.message;
            }
        } finally {
            if (
                renderId
                === state.drawerRenderId
            ) {
                setDrawerLoading(false);
            }
        }
    };

    const closeDrawer = () => {
        if (invitationUi?.busy) return;
        if (inlineEditor && !elements.drawer.classList.contains('is-open')) return;
        const formulaWizard = $("[data-charge-formula-builder]", elements.formFields);
        if (formulaWizard?.requestFormulaClose && !formulaWizard.requestFormulaClose()) return;
        elements.form.chargeWizardDestroy?.();
        invitationUi?.destroy();
        state.drawerRenderId += 1;

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
        editorVisibility(false);

        state.editRow =
            null;

        setDrawerLoading(false);
        if (!inlineEditor) unlockPageScroll();

        if (
            state.drawerTrigger
                ?.isConnected
        ) {
            state.drawerTrigger.focus({
                preventScroll: true,
            });
        }

        state.drawerTrigger =
            null;
    };

    const saveForm = async (
        event
    ) => {
        event.preventDefault();
        if (elements.saveButton.disabled) return;
        if (invitationUi && !invitationUi.prepareSubmit()) return;
        if (coreResource) core?.showErrors(elements.formFields, {});
        const formulaWizard = $("[data-charge-formula-builder]", elements.formFields);
        if (formulaWizard?.prepareFormulaSubmit && !formulaWizard.prepareFormulaSubmit()) return;

        const mode =
            elements.form
                .dataset
                .mode
            || "create";

        const endpointConfig =
            mode === "edit"
                ? resource.update
                : resource.create;

        if (!formulaPermission(mode === 'edit' ? 'update' : 'create')) return;

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

            if (coreResource) Object.assign(payload, core?.defaults(bootstrap.resourceKey, payload, mode));

            const endpoint =
                interpolate(
                    endpointConfig.url,
                    state.editRow
                );

            elements.saveButton.disabled =
                true;
            elements.saveButton.textContent =
                "در حال ذخیره...";
            formulaWizard?.setFormulaBusy?.(true);
            invitationUi?.setBusy(true);

            const { payload: savedPayload } = await apiFetch(
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

            lookupCache.clear();
            if (coreResource) core?.saved(bootstrap.resourceKey, savedPayload?.data || savedPayload, mode);

            toast(
                bootstrap.resourceKey === "charge-formulas"
                    ? payload.is_active ? "فرمول ذخیره و فعال شد؛ در محاسبه دوره‌های ساختمان استفاده می‌شود." : "فرمول به‌صورت غیرفعال ذخیره شد."
                    : bootstrap.resourceKey === "invitations"
                && mode === "create"
                && savedPayload?.meta?.sent_count
                    ? `دعوت‌نامه برای ${formulaNumber(savedPayload.meta.sent_count)} کاربر ارسال شد.`
                    : bootstrap.resourceKey === "facility-schedules"
                    && mode === "create"
                    && savedPayload?.meta?.created_count
                    ? `${formulaNumber(savedPayload.meta.created_count)} برنامه زمان‌بندی با موفقیت ثبت شد.`
                    : mode === "edit"
                    ? "تغییرات با موفقیت ذخیره شد."
                    : "رکورد با موفقیت ثبت شد."
            );

            formulaWizard?.markFormulaSaved?.();
            formulaWizard?.setFormulaBusy?.(false);
            invitationUi?.setBusy(false);
            closeDrawer();

            if (resource.list) {
                await loadRows();
                if (inlineEditor) $("#charge-directory-title")?.focus();
            }
            if (event.submitter?.dataset.coreSaveAnother === '1') await openDrawer('create');
        } catch (error) {
            elements.formError
                .textContent =
                formulaWizard ? "" : error.message;
            if (coreResource) core?.showErrors(elements.formFields, error.payload?.errors || {});
            invitationUi?.showErrors(error.payload?.errors || {});
            formulaWizard?.showFormulaErrors?.(error.payload?.errors || {}, error.message);
        } finally {
            elements.saveButton.disabled =
                false;
            invitationUi?.setBusy(false);
            formulaWizard?.setFormulaBusy?.(false);
            elements.saveButton.textContent =
                mode === "edit"
                    ? "ذخیره تغییرات"
                    : "ثبت رکورد";
            formulaWizard?.syncFormulaFooter?.();
            invitationUi?.sync();
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

            lookupCache.clear();

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

            lookupCache.clear();

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
        if (elements.createButton) elements.createButton.disabled = !resource.create;

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

                if (
                    event.key !== "Tab"
                    || inlineEditor
                    || ! elements.drawer
                        ?.classList
                        .contains("is-open")
                ) {
                    return;
                }

                const focusable = $$([
                    "button:not([disabled])",
                    "input:not([disabled])",
                    "select:not([disabled])",
                    "textarea:not([disabled])",
                    "a[href]",
                    "[tabindex]:not([tabindex='-1'])",
                ].join(","), elements.drawer)
                    .filter((element) =>
                        element.offsetParent
                        !== null
                    );

                if (! focusable.length) {
                    event.preventDefault();
                    elements.drawer.focus();
                    return;
                }

                const first = focusable[0];
                const last =
                    focusable[
                        focusable.length - 1
                    ];

                if (
                    event.shiftKey
                    && document.activeElement
                        === first
                ) {
                    event.preventDefault();
                    last.focus();
                } else if (
                    ! event.shiftKey
                    && document.activeElement
                        === last
                ) {
                    event.preventDefault();
                    first.focus();
                }
            }
        );
    };

    const renderRemotePagination = () => {
        if (!coreResource && !scopedFormula) return;
        let pager = $("#coreRemotePagination");
        if (!pager) {
            pager = document.createElement("div"); pager.id = "coreRemotePagination"; pager.className = "core-flow__actions";
            elements.tableWrap.after(pager);
        }
        pager.replaceChildren();
        if (state.remoteLastPage <= 1) return;
        for (const [title, page] of [["صفحه قبلی فهرست", state.remotePage - 1], ["صفحه بعدی فهرست", state.remotePage + 1]]) {
            const button = document.createElement("button"); button.type = "button"; button.textContent = title;
            button.disabled = state.loading || page < 1 || page > state.remoteLastPage;
            button.addEventListener("click", () => {
                const endpoint = new URL(listEndpoint(), window.location.origin); endpoint.searchParams.set("page", String(page));
                state.remoteEndpoint = endpoint.pathname + endpoint.search; void loadRows();
            });
            pager.append(button);
        }
        const label = document.createElement("span");
        label.textContent = `صفحه ${state.remotePage} از ${state.remoteLastPage}؛ جستجو در موارد همین صفحه`;
        pager.append(label);
    };

    const init = async () => {
        initEvents();

        if (coreResource && elements.createButton && !resource.create) elements.createButton.hidden = true;

        if (coreResource) await core?.mount(bootstrap.resourceKey);

        await initContextLookups();

        for (const select of $$("[data-context-name]")) {
            const value = new URL(window.location.href).searchParams.get(select.dataset.contextName) || core?.parentValue(bootstrap.resourceKey);
            if (value && [...select.options].some((option) => Number(option.value) === Number(value))) {
                select.value = String(value);
                select.dispatchEvent(new Event("change", { bubbles: true }));
            }
        }
        window.addEventListener("buildino:flow-create", (event) => {
            if (event.detail.resourceKey === bootstrap.resourceKey && resource.create) void openDrawer("create");
        });

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
        app.dataset.coreReady = '1';

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
