export class Datepicker {
    constructor(selector, initialDate = null, {
        weeklabels = [],
        monthlabels = [],
        minDate = null,
        maxDate = null,
        disabled = false,
        inputName = null
    } = {}) {
        this.container = document.querySelector(selector);

        this.viewMode = "days"; // puede ser: days, months, years

        this.minDate = minDate instanceof Date ? this.clearTime(minDate) : null;
        this.maxDate = maxDate instanceof Date ? this.clearTime(maxDate) : null;
        this.disabled = disabled;
        this.inputName = inputName;

        this.selectedDate = this.parseInitialDate(initialDate);
        this.lastValidSelectedDate = this.selectedDate ? new Date(this.selectedDate) : null;

        const baseDate = this.selectedDate ?? new Date();
        this.currYear = baseDate.getFullYear();
        this.currMonth = baseDate.getMonth();

        this.weeklabels = weeklabels.length === 7 ? weeklabels : ["Dom", "Lun", "Mar", "Mié", "Jue", "Vie", "Sáb"];
        this.months = monthlabels.length === 12 ? monthlabels : [
            "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
            "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
        ];

        this.build();
        this.render();
        this.attachEvents();
    }

    clearTime(date) {
        return new Date(date.getFullYear(), date.getMonth(), date.getDate());
    }

    parseInitialDate(date) {
        if (date instanceof Date && !isNaN(date)) return new Date(date);
        if (typeof date === "number" && !isNaN(date)) return new Date(date);
        if (typeof date === "string" && /^\d{2}\/\d{2}\/\d{4}$/.test(date)) {
            const [dd, mm, yyyy] = date.split("/").map(Number);
            const parsed = new Date(yyyy, mm - 1, dd);
            return (parsed.getFullYear() === yyyy && parsed.getMonth() === mm - 1 && parsed.getDate() === dd) ? parsed : null;
        }
        return null;
    }

    build() {
        this.container.classList.add("datepicker");
        this.container.innerHTML = `
            <div class="datepicker-input">
                <input type="text" placeholder="dd/mm/yyyy" maxlength="10" ${this.inputName ? `name="${this.inputName}"` : ""}>
                <i class="bi bi-calendar3"></i>
            </div>
            <div class="datepicker-container">
                <div>
                    <p class="current-date"></p>
                    <div class="icons">
                        <span id="prev"></span>
                        <span id="next"></span>
                    </div>
                </div>
                <div class="calendar">
                    <ul class="weeks">${this.weeklabels.map(day => `<li>${day}</li>`).join('')}</ul>
                    <ul class="days"></ul>
                </div>
            </div>`;

        this.input = this.container.querySelector("input");
        this.icon = this.container.querySelector(".bi-calendar3");
        this.datepickerContainer = this.container.querySelector(".datepicker-container");
        this.currentDate = this.container.querySelector(".current-date");
        this.daysTag = this.container.querySelector(".days");
        this.prevNext = this.container.querySelectorAll(".icons span");

        if (this.disabled) {
            this.input.setAttribute("disabled", "true");
            this.container.classList.add("datepicker-disabled");
        }

    }

    verifyView() {
        const weeksEl = this.container.querySelector(".weeks");
        if (weeksEl)
            weeksEl.style.display = this.viewMode === "days" ? "flex" : "none";
    }

    render() {
        if (this.viewMode === "days") return this.renderDays();
        if (this.viewMode === "months") return this.renderMonths();
        if (this.viewMode === "years") return this.renderYears();
    }

    renderDays() {
        this.viewMode = "days";
        this.verifyView();
        this.container.classList.remove("datepicker-years-view");

        const firstDay = new Date(this.currYear, this.currMonth, 1).getDay();
        const lastDate = new Date(this.currYear, this.currMonth + 1, 0).getDate();
        const lastDay = new Date(this.currYear, this.currMonth, lastDate).getDay();
        const prevMonthLastDate = new Date(this.currYear, this.currMonth, 0).getDate();

        let liTag = "";

        // Días del mes anterior
        for (let i = firstDay; i > 0; i--) {
            const date = new Date(this.currYear, this.currMonth - 1, prevMonthLastDate - i + 1);
            const clean = this.clearTime(date);
            const isDisabled = (this.minDate && clean < this.minDate) || (this.maxDate && clean > this.maxDate);

            liTag += `<li class="inactive ${isDisabled ? "disabled" : ""}" data-day="${prevMonthLastDate - i + 1}" data-offset="-1">${prevMonthLastDate - i + 1}</li>`;
        }

        // Días del mes actual (sin cambios mayores)
        for (let i = 1; i <= lastDate; i++) {
            const date = new Date(this.currYear, this.currMonth, i);
            const clean = this.clearTime(date);

            const isSelected = this.selectedDate && i === this.selectedDate.getDate() &&
                this.currMonth === this.selectedDate.getMonth() && this.currYear === this.selectedDate.getFullYear();

            const isToday = i === new Date().getDate() &&
                this.currMonth === new Date().getMonth() &&
                this.currYear === new Date().getFullYear();

            const isDisabled = (this.minDate && clean < this.minDate) || (this.maxDate && clean > this.maxDate);

            liTag += `<li class="${isSelected ? "active" : ""} ${isToday ? "today" : ""} ${isDisabled ? "disabled" : ""}" data-day="${i}" data-offset="0">${i}</li>`;
        }

        // Días del mes siguiente
        for (let i = lastDay; i < 6; i++) {
            const date = new Date(this.currYear, this.currMonth + 1, i - lastDay + 1);
            const clean = this.clearTime(date);
            const isDisabled = (this.minDate && clean < this.minDate) || (this.maxDate && clean > this.maxDate);

            liTag += `<li class="inactive ${isDisabled ? "disabled" : ""}" data-day="${i - lastDay + 1}" data-offset="1">${i - lastDay + 1}</li>`;
        }

        this.currentDate.innerText = `${this.months[this.currMonth]} ${this.currYear}`;
        this.daysTag.innerHTML = liTag;
        this.attachDaySelection();

        if (this.selectedDate) {
            this.input.value = this.formatDate(this.selectedDate);
        } else {
            this.input.value = "";
        }

        this.updateNavigationControls();
    }

    renderMonths() {
        this.viewMode = "months";
        this.verifyView();
        this.container.classList.remove("datepicker-years-view");

        const monthsGrid = this.months.map((label, i) => {
            const isDisabled = !this.isMonthInRange(this.currYear, i);

            const isActive = this.selectedDate &&
                this.currYear === this.selectedDate.getFullYear() &&
                i === this.selectedDate.getMonth();

            const classes = [
                isDisabled ? "disabled" : "",
                isActive ? "active" : ""
            ].filter(Boolean).join(" ");

            return `<li data-month="${i}" class="${classes}">${label.slice(0, 3).toLowerCase()}.</li>`;
        }).join("");

        this.daysTag.innerHTML = `<ul class="month-grid">${monthsGrid}</ul>`;
        this.currentDate.innerText = `${this.currYear}`;
        this.attachMonthSelection();
        this.updateNavigationControls();
    }

    renderYears() {
        this.viewMode = "years";
        this.verifyView();
        this.container.classList.add("datepicker-years-view");

        const base = Math.floor(this.currYear / 12) * 12;
        let years = "";

        for (let i = 0; i < 12; i++) {
            const year = base + i;
            const isDisabled = !this.isAnyMonthInRange(year);

            const isActive = this.selectedDate && year === this.selectedDate.getFullYear();

            const classes = [
                isDisabled ? "disabled" : "",
                isActive ? "active" : ""
            ].filter(Boolean).join(" ");

            years += `<li data-year="${year}" class="${classes}">${year}</li>`;
        }

        this.daysTag.innerHTML = `<ul class="year-grid">${years}</ul>`;
        this.currentDate.innerText = `${base} - ${base + 11}`;
        this.attachYearSelection();
        this.updateNavigationControls();
    }

    attachEvents() {
        this.icon.addEventListener("click", () => {
            if (this.disabled) return;
            this.container.classList.toggle("open");
        });

        this.input.addEventListener("focus", () => {
            if (this.disabled) return;
            this.container.classList.add("open");
        });

        this.prevNext.forEach(icon => icon.addEventListener("click", () => {
            if (icon.classList.contains("disabled")) return;

            if (this.viewMode === "days") {
                this.currMonth += icon.id === "prev" ? -1 : 1;
                const date = new Date(this.currYear, this.currMonth);
                this.currYear = date.getFullYear();
                this.currMonth = date.getMonth();
                this.render();
            } else if (this.viewMode === "months") {
                this.currYear += icon.id === "prev" ? -1 : 1;
                this.render();
            } else if (this.viewMode === "years") {
                this.currYear += icon.id === "prev" ? -12 : 12;
                this.render();
            }
        }));

        this.currentDate.addEventListener("click", () => {
            if (this.viewMode === "days") this.renderMonths();
            else if (this.viewMode === "months") this.renderYears();
        });

        this.input.addEventListener("input", () => this.handleInput());

        let isInternalClick = false;
        this.datepickerContainer.addEventListener("mousedown", () => {
            isInternalClick = true;
        });

        this.datepickerContainer.addEventListener("mouseup", () => {
            isInternalClick = false;
        });

        this.input.addEventListener("blur", () => {
            this.handleBlur();

            if (!isInternalClick && !this.container.contains(document.activeElement)) {
                this.close();
            }
        });

        this.input.addEventListener("keydown", (e) => {
            if (e.key === "Enter") {
                this.handleInput(); 
                this.input.blur(); 
            }
        });

        document.addEventListener("click", (e) => {
            if (!this.container.contains(e.target)) {
                this.close();
            }
        });

        this.setupStrictInputValidation();
    }

    close() {
        this.container.classList.remove("open");

        if (this.selectedDate) {
            this.currYear = this.selectedDate.getFullYear();
            this.currMonth = this.selectedDate.getMonth();
        } else if (this.lastValidSelectedDate) {
            this.selectedDate = new Date(this.lastValidSelectedDate);
            this.input.value = this.formatDate(this.selectedDate);
            this.markInputValid();
            this.currYear = this.selectedDate.getFullYear();
            this.currMonth = this.selectedDate.getMonth();
        }
        else {
            const today = new Date();
            this.currYear = today.getFullYear();
            this.currMonth = today.getMonth();
            this.input.value = "";
            this.markInputValid();
        }

        this.viewMode = "days";
        this.render();
    }

    attachDaySelection() {
        this.daysTag.querySelectorAll("li:not(.disabled)").forEach(li => {
            li.addEventListener("click", () => {
                const day = parseInt(li.dataset.day);
                const offset = parseInt(li.dataset.offset); // puede ser -1, 0, 1

                const targetMonth = this.currMonth + offset;
                const targetDate = new Date(this.currYear, targetMonth, day);
                const clean = this.clearTime(targetDate);

                if ((this.minDate && clean < this.minDate) || (this.maxDate && clean > this.maxDate)) return;

                this.selectedDate = targetDate;
                this.lastValidSelectedDate = new Date(targetDate);
                this.currYear = targetDate.getFullYear();
                this.currMonth = targetDate.getMonth();
                this.input.value = this.formatDate(this.selectedDate);

                this.markInputValid();

                this.container.classList.remove("open");
                this.render();
            });
        });
    }

    attachMonthSelection() {
        this.container.querySelectorAll("[data-month]").forEach(el => {
            if (el.classList.contains("disabled")) return;

            el.addEventListener("click", (e) => {
                e.stopPropagation();
                this.currMonth = parseInt(el.dataset.month);
                this.renderDays();
            });
        });
    }

    attachYearSelection() {
        this.container.querySelectorAll("[data-year]").forEach(el => {
            if (el.classList.contains("disabled")) return;

            el.addEventListener("click", (e) => {
                e.stopPropagation();
                this.currYear = parseInt(el.dataset.year);
                this.renderMonths();
            });
        });
    }

    handleInput() {
        const val = this.input.value.replace(/[^0-9/]/g, "").slice(0, 10);
        this.input.value = val;
        this.validateProgressive(val);

        if (/^\d{2}\/\d{2}\/\d{4}$/.test(val) && this.isValidDateInput(val)) {
            const [dd, mm, yyyy] = val.split("/").map(Number);
            const parsed = new Date(yyyy, mm - 1, dd);
            const clean = this.clearTime(parsed);

            if ((this.minDate && clean < this.minDate) || (this.maxDate && clean > this.maxDate)) {
                this.markInputInvalid();
                this.selectedDate = null;
                return;
            }

            this.selectedDate = parsed;
            this.lastValidSelectedDate = new Date(parsed);
            this.currYear = yyyy;
            this.currMonth = mm - 1;
            this.render();
            this.markInputValid();
        } else {
            this.selectedDate = null;
        }
    }

    handleBlur() {
        const val = this.input.value;
        if (!val || !this.isValidDateInput(val)) {
            this.selectedDate = null;
            this.markInputInvalid();

            if (this.lastValidSelectedDate) {
                this.selectedDate = new Date(this.lastValidSelectedDate);
                this.input.value = this.formatDate(this.selectedDate);
                this.markInputValid();
            } else {
                this.input.value = "";
            }
            return;
        }

        const [dd, mm, yyyy] = val.split("/").map(Number);
        const parsed = new Date(yyyy, mm - 1, dd);
        const clean = this.clearTime(parsed);

        if ((this.minDate && clean < this.minDate) || (this.maxDate && clean > this.maxDate)) {
            this.markInputInvalid();
            this.selectedDate = null;

            if (this.lastValidSelectedDate) {
                this.selectedDate = new Date(this.lastValidSelectedDate);
                this.input.value = this.formatDate(this.selectedDate);
                this.markInputValid();
            } else {
                this.input.value = "";
            }
            return;
        }

        this.selectedDate = parsed;
        this.lastValidSelectedDate = new Date(parsed);
        this.currYear = parsed.getFullYear();
        this.currMonth = parsed.getMonth();
        this.markInputValid();
    }

    formatDate(date) {
        const dd = String(date.getDate()).padStart(2, '0');
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const yyyy = date.getFullYear();
        return `${dd}/${mm}/${yyyy}`;
    }

    isValidDateInput(str) {
        if (!/^\d{2}\/\d{2}\/\d{4}$/.test(str)) return false;
        const [dd, mm, yyyy] = str.split('/').map(Number);
        const date = new Date(yyyy, mm - 1, dd);
        return date.getFullYear() === yyyy && date.getMonth() === mm - 1 && date.getDate() === dd && yyyy >= 1000 && yyyy <= 2100;
    }

    isMonthInRange(year, month) {
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        for (let day = 1; day <= daysInMonth; day++) {
            const date = this.clearTime(new Date(year, month, day));
            const afterMin = !this.minDate || date >= this.minDate;
            const beforeMax = !this.maxDate || date <= this.maxDate;
            if (afterMin && beforeMax) return true;
        }

        return false; // ningún día válido en este mes
    }

    isAnyMonthInRange(year) {
        for (let month = 0; month < 12; month++) {
            if (this.isMonthInRange(year, month)) return true;
        }
        return false;
    }

    validateProgressive(val) {
        const isPartial = /^\d{0,2}(\/\d{0,2}(\/\d{0,4})?)?$/.test(val);
        const isFullValid = val.length === 10 && this.isValidDateInput(val);
        const warn = !isPartial || (val.length === 10 && !isFullValid);
        this.input.style.backgroundColor = warn ? "#FDF1EE" : "";
        this.input.style.border = warn ? "1px solid #FD8B89" : "";
    }

    setupStrictInputValidation() {
        this.input.addEventListener("beforeinput", (e) => {
            if (e.inputType === "insertText" && e.data) {
                const newValue = this.getNewInputValue(e);
                if (newValue.length > 10 || !/^\d{0,2}(\/\d{0,2}(\/\d{0,4})?)?$/.test(newValue)) {
                    e.preventDefault();
                    return;
                }
                setTimeout(() => this.validateProgressive(this.input.value), 0);
            } else if (e.inputType.startsWith("delete") || e.inputType === "insertFromPaste") {
                setTimeout(() => {
                    if (this.input.value.length > 10) this.input.value = this.input.value.slice(0, 10);
                    this.validateProgressive(this.input.value);
                }, 0);
            }
        });
    }

    getNewInputValue(e) {
        const input = e.target;
        const { selectionStart: start, selectionEnd: end, value: current } = input;
        return current.slice(0, start) + e.data + current.slice(end);
    }

    markInputValid() {
        this.input.style.backgroundColor = "";
        this.input.style.border = "";
    }

    markInputInvalid() {
        this.input.style.backgroundColor = "#FDF1EE";
        this.input.style.border = "1px solid #FD8B89";
    }

    updateNavigationControls() {
        const prevBtn = this.container.querySelector("#prev");
        const nextBtn = this.container.querySelector("#next");

        let canGoPrev = true;
        let canGoNext = true;

        if (this.viewMode === "days") {
            const prevDate = new Date(this.currYear, this.currMonth - 1, 1);
            const nextDate = new Date(this.currYear, this.currMonth + 1, 1);

            canGoPrev = this.isMonthInRange(prevDate.getFullYear(), prevDate.getMonth());
            canGoNext = this.isMonthInRange(nextDate.getFullYear(), nextDate.getMonth());

        } else if (this.viewMode === "months") {
            const prevYear = this.currYear - 1;
            const nextYear = this.currYear + 1;

            canGoPrev = this.isAnyMonthInRange(prevYear);
            canGoNext = this.isAnyMonthInRange(nextYear);

        } else if (this.viewMode === "years") {
            const base = Math.floor(this.currYear / 12) * 12;
            const prevStart = base - 12;
            const nextStart = base + 12;

            canGoPrev = this.isAnyYearRangeInRange(prevStart, prevStart + 11);
            canGoNext = this.isAnyYearRangeInRange(nextStart, nextStart + 11);
        }

        prevBtn.classList.toggle("disabled", !canGoPrev);
        nextBtn.classList.toggle("disabled", !canGoNext);
    }

    isAnyYearRangeInRange(start, end) {
        for (let year = start; year <= end; year++) {
            if (this.isAnyMonthInRange(year)) return true;
        }
        return false;
    }
}