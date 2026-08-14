
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
        pagination: $("#crudPagination"),
        summary: $("#crudRecordSummary"),
        currentPage: $("#crudCurrentPage"),
        prevPage: $("#crudPrevPage"),
        nextPage: $("#crudNextPage"),
        pageSize: $("#crudPageSize"),
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
        pageSize: Number(elements.pageSize?.value || 25),
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

            if (elements.pagination) {
                elements.pagination.hidden = true;
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

        if (elements.pagination) {
            elements.pagination.hidden = true;
        }
    };

    const toast = (
        message,
        tone = "success"
    ) => {
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

    const enhanceLookupSearch = (
        select
    ) => {
        if (
            ! select
            || select.dataset
                .lookupEnhanced
                === "1"
            || select.options.length
                < 12
        ) {
            return;
        }

        const wrapper =
            select.parentElement;

        if (! wrapper) {
            return;
        }

        const search =
            document.createElement(
                "input"
            );

        search.type =
            "search";

        search.className =
            "crud-lookup-filter";

        search.placeholder =
            "جستجو در گزینه‌ها...";

        search.autocomplete =
            "off";

        search.addEventListener(
            "input",
            () => {
                const term =
                    String(
                        search.value
                        || ""
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

                [...select.options]
                    .forEach(
                        (option) => {
                            if (
                                option.value
                                === ""
                            ) {
                                option.hidden =
                                    false;
                                return;
                            }

                            const label =
                                String(
                                    option.textContent
                                    || ""
                                )
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

                            option.hidden =
                                term
                                ? ! label.includes(
                                    term
                                )
                                : false;
                        }
                    );
            }
        );

        select.before(
            search
        );

        select.dataset
            .lookupEnhanced =
            "1";
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

            enhanceLookupSearch(
                select
            );
        } catch (error) {
            select.innerHTML =
                placeholder
                + `<option value="" disabled>خطا در دریافت گزینه‌ها</option>`;
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

        if (elements.pagination) {
            elements.pagination.hidden =
                false;
        }

        const total =
            state.filteredRows.length;

        const from =
            total
                ? start + 1
                : 0;

        const to =
            Math.min(
                start + state.pageSize,
                total
            );

        if (elements.summary) {
            elements.summary.textContent =
                `نمایش ${from} تا ${to} از ${total} رکورد`;
        }

        if (elements.currentPage) {
            elements.currentPage.textContent =
                state.currentPage;
        }

        if (elements.prevPage) {
            elements.prevPage.disabled =
                state.currentPage <= 1;
        }

        if (elements.nextPage) {
            elements.nextPage.disabled =
                to >= total;
        }

        bindTableActions();
    };

    const findRow = (id) =>
        state.rows.find(
            (row) =>
                String(row.id)
                === String(id)
        );

    const createFieldElement = async (
        field,
        value = null,
        mode = "create"
    ) => {
        if (
            mode === "edit"
            && field.create_only
        ) {
            return null;
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

            input.type =
                field.type
                || "text";
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
                    mode
                );

            if (element) {
                container.appendChild(
                    element
                );
            }
        }
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
            ! window.confirm(
                "رکورد انتخاب‌شده حذف شود؟ این عملیات ممکن است قابل بازگشت نباشد."
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
        if (
            action.confirm
            && ! (action.fields || []).length
        ) {
            if (
                ! window.confirm(
                    action.confirm
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
            && ! window.confirm(
                action.confirm
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

        elements.pageSize
            ?.addEventListener(
                "change",
                () => {
                    state.pageSize =
                        Number(
                            elements
                                .pageSize
                                .value
                        );
                    state.currentPage =
                        1;
                    renderTable();
                }
            );

        elements.prevPage
            ?.addEventListener(
                "click",
                () => {
                    if (
                        state.currentPage
                        > 1
                    ) {
                        state.currentPage -=
                            1;
                        renderTable();
                    }
                }
            );

        elements.nextPage
            ?.addEventListener(
                "click",
                () => {
                    const max =
                        Math.ceil(
                            state
                                .filteredRows
                                .length
                            / state.pageSize
                        );

                    if (
                        state.currentPage
                        < max
                    ) {
                        state.currentPage +=
                            1;
                        renderTable();
                    }
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
