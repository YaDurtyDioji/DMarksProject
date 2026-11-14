(() => {
    'use strict';

    const CSRF = document.querySelector('meta[name=csrf]').content;
    const $ = id => document.getElementById(id);

    // Theme Toggle
    const themeBtn = $('theme-toggle');
    const savedTheme = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-bs-theme', savedTheme);
    updateThemeIcon(savedTheme);

    themeBtn.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-bs-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', next);
        localStorage.setItem('theme', next);
        updateThemeIcon(next);
    });

    function updateThemeIcon(theme) {
        themeBtn.innerHTML = theme === 'dark' ? '<i class="bi bi-moon-stars-fill"></i>' : '<i class="bi bi-sun-fill"></i>';
    }

    // Elements
    const exerciseList = $('exercise-list');
    const routineList  = $('routine-list');
    const searchInput  = $('search');
    const bodyPartSel  = $('filter-bodypart');
    const categorySel  = $('filter-category');
    const resultCount  = $('result-count');
    const routineName  = $('routine-name');
    const loadSelect   = $('load-routine');
    const btnSave      = $('btn-save');
    const btnDelete    = $('btn-delete');
    const statusEl     = $('status');
    const userWeight   = $('user-weight');

    let currentRoutineId = null;
    let searchTimer = null;

    /* ============ Catalogue ========================================== */
    async function loadExercises() {
        const params = new URLSearchParams({
            action: 'search',
            q: searchInput.value.trim(),
            bodyPart: bodyPartSel.value,
            category: categorySel.value,
        });
        try {
            const data = await (await fetch(`api.php?${params}`)).json();
            resultCount.textContent = `${data.length.toLocaleString()} exercises`;

            if (!data.length) {
                exerciseList.innerHTML = `<div class="empty-state"><i class="bi bi-search"></i><div>No results.</div></div>`;
                return;
            }
            exerciseList.innerHTML = data.map(catalogueCardHtml).join('');
        } catch (err) {
            exerciseList.innerHTML = `<div class="alert alert-danger m-0">Error loading exercises.</div>`;
        }
    }

    searchInput.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(loadExercises, 220); });
    bodyPartSel.addEventListener('change', loadExercises);
    categorySel.addEventListener('change', loadExercises);
    loadExercises();

    /* ============ Drag & Drop Setup ================================== */
    // Clone from catalogue
    new Sortable(exerciseList, {
        group: { name: 'exercises', pull: 'clone', put: false },
        sort: false,
        animation: 150,
    });

    // Main Routine Container (handles blocks)
    new Sortable(routineList, {
        group: 'blocks',
        handle: '.block-handle',
        animation: 150,
        onAdd: (evt) => {
            // If dropped directly into routine list, wrap in a block
            const item = evt.item;
            if (item.classList.contains('exercise-card')) {
                const block = createBlock();
                block.querySelector('.block-exercises').appendChild(item);
                routineList.insertBefore(block, routineList.children[evt.newIndex]);
                setupExerciseCard(item);
            }
            removeEmptyState();
        }
    });

    // Helper to create an empty block
    function createBlock() {
        const block = document.createElement('div');
        block.className = 'superset-block mb-3';
        block.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-2 px-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-grip-vertical drag-handle block-handle"></i>
                    <span class="fw-bold text-primary small">SUPERSET BLOCK</span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger remove-block"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="block-exercises sortable-nested"></div>
        `;
        
        // Setup nested sortable for exercises inside this block
        new Sortable(block.querySelector('.block-exercises'), {
            group: { name: 'exercises', pull: true, put: true },
            handle: '.drag-handle',
            animation: 150,
            onAdd: (evt) => setupExerciseCard(evt.item),
            onRemove: (evt) => {
                // If a block becomes empty, remove it
                if (!evt.from.querySelector('.exercise-card')) {
                    evt.from.closest('.superset-block').remove();
                }
            }
        });

        block.querySelector('.remove-block').addEventListener('click', () => block.remove());
        return block;
    }

    function removeEmptyState() {
        routineList.querySelector('.empty-state')?.remove();
    }

    function setupExerciseCard(card) {
        if (card.dataset.setup) return; // Prevent double setup
        card.dataset.setup = 'true';
        card.classList.add('mb-2');
        
        const setsCount = 3; // Default
        const weight = userWeight.value || 75;
        const id = card.dataset.id;
        const met = getMetEstimate(card.dataset.category, card.dataset.equipment);
        
        // Add Drag Handle and Remove Button to header
        const header = card.querySelector('.exercise-header');
        header.insertAdjacentHTML('afterbegin', `<i class="bi bi-grip-vertical drag-handle me-2"></i>`);
        header.insertAdjacentHTML('beforeend', `<button type="button" class="btn btn-sm btn-outline-danger remove-card ms-2"><i class="bi bi-x-lg"></i></button>`);

        card.querySelector('.remove-card').addEventListener('click', () => {
            card.remove();
            if (!routineList.querySelector('.exercise-card')) {
                routineList.innerHTML = `<div class="empty-state"><i class="bi bi-plus-circle"></i><div class="fw-semibold">Drag exercises here</div></div>`;
            }
        });

        // Build Sets Area
        const setsArea = card.querySelector('.sets-container');
        setsArea.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-2 mt-3">
                <label class="small text-secondary fw-bold">Sets</label>
                <input type="number" class="form-control form-control-sm set-count-input" style="width: 70px;" value="${setsCount}" min="1" max="10">
            </div>
            <div class="set-rows"></div>
            <div class="text-end small text-secondary mt-2">
                <i class="bi bi-fire"></i> ~<span class="cal-total">0</span> kcal
            </div>
        `;

        // Generate rows
        const rowsContainer = setsArea.querySelector('.set-rows');
        function renderRows(count) {
            rowsContainer.innerHTML = '';
            for (let i = 1; i <= count; i++) {
                rowsContainer.insertAdjacentHTML('beforeend', `
                    <div class="set-row">
                        <div class="set-num">${i}</div>
                        <input type="text" class="form-control form-control-sm set-weight" placeholder="kg" value="">
                        <input type="text" class="form-control form-control-sm set-reps" placeholder="reps/sec" value="">
                        <input type="number" class="form-control form-control-sm set-rpe" placeholder="RPE" min="1" max="10" step="0.5">
                        <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="this.parentElement.remove(); recalcCard()"><i class="bi bi-dash-circle"></i></button>
                    </div>
                `);
            }
            recalcCard();
        }

        function recalcCard() {
            const rows = rowsContainer.querySelectorAll('.set-row').length;
            setsArea.querySelector('.set-count-input').value = rows;
            
            // Simple estimate: ~5 mins per 3 sets * MET * weight(kg) * 1.05
            // 1 MET ~= 1 kcal/kg/hr
            const durationHours = (rows * 1.5) / 60; // Approx 1.5 mins per set
            const cal = Math.round(met * weight * durationHours);
            setsArea.querySelector('.cal-total').textContent = cal;
        }

        setsArea.querySelector('.set-count-input').addEventListener('change', (e) => {
            renderRows(parseInt(e.target.value) || 1);
        });
        
        // Attach recalculation to inputs
        setsArea.addEventListener('input', (e) => {
            if (e.target.classList.contains('set-weight') || e.target.classList.contains('set-reps')) {
                recalcCard();
            }
        });

        renderRows(setsCount);
    }

    // MET Estimator based on openGym categories
    function getMetEstimate(category, equipment) {
        category = (category || '').toLowerCase();
        equipment = (equipment || '').toLowerCase();
        
        if (category.includes('cardio') || category.includes('plyometrics')) return 8.0;
        if (category.includes('yoga') || category.includes('stretching')) return 3.0;
        if (category.includes('pilates')) return 3.5;
        if (category.includes('strength') || category.includes('powerlifting')) {
            return (equipment.includes('body') || equipment.includes('none')) ? 4.0 : 6.0;
        }
        if (category.includes('running')) return 9.8;
        return 5.0; // Default
    }

    /* ============ Load & Save ======================================== */
    // (Implement loadRoutineList, loadRoutine, and save logic as before, but serialize the nested blocks)
    
    btnSave.addEventListener('click', async () => {
        const blocks = routineList.querySelectorAll('.superset-block');
        if (!blocks.length) return showStatus('Add at least one exercise.', 'error');

        const exercises = [];
        let position = 0;

        // Loop through Blocks
        blocks.forEach((block, bIndex) => {
            const cards = block.querySelectorAll('.exercise-card');
            
            cards.forEach((card, cIndex) => {
                const sets = [];
                card.querySelectorAll('.set-row').forEach(row => {
                    sets.push({
                        weight: row.querySelector('.set-weight').value,
                        reps: row.querySelector('.set-reps').value,
                        rpe: row.querySelector('.set-rpe').value
                    });
                });

                exercises.push({
                    id: card.dataset.id,
                    position: position++,
                    block_id: bIndex,       // Determines superset grouping
                    block_order: cIndex,    // Order within superset
                    sets_data: sets
                });
            });
        });

        const body = { name: routineName.value.trim(), exercises };
        if (currentRoutineId !== null) body.id = currentRoutineId;

        btnSave.disabled = true;
        try {
            const res = await fetch('api.php?action=save-routine', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                body: JSON.stringify(body),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Save failed');
            
            currentRoutineId = data.routine_id;
            btnDelete.hidden = false;
            showStatus('Routine saved.', 'ok');
        } catch (err) {
            showStatus(err.message || 'Error saving', 'error');
        } finally {
            btnSave.disabled = false;
        }
    });

    /* ============ Helpers ============================================ */
    function catalogueCardHtml(ex) {
        return `
            <div class="exercise-card card border-secondary-subtle bg-body-tertiary mb-2" 
                 data-id="${ex.id}" data-category="${ex.category}" data-equipment="${ex.equipment}">
                <div class="card-body p-2">
                    <div class="fw-semibold small">${esc(ex.name)}</div>
                    <div class="d-flex flex-wrap gap-1 mt-1">
                        ${ex.body_part ? `<span class="badge bg-primary-subtle text-primary-emphasis fw-normal">${esc(ex.body_part)}</span>` : ''}
                        ${ex.category ? `<span class="badge bg-warning-subtle text-warning-emphasis fw-normal">${esc(ex.category)}</span>` : ''}
                    </div>
                </div>
            </div>`;
    }

    function setupExerciseCard(card) {
        if (card.dataset.setup) return;
        card.dataset.setup = 'true';
        
        // Convert raw catalogue HTML into routine card HTML
        const id = card.dataset.id;
        const name = card.querySelector('.fw-semibold').textContent;
        const cat = card.dataset.category || '';
        const eq = card.dataset.equipment || '';

        card.innerHTML = `
            <div class="card-body p-2">
                <div class="exercise-header d-flex align-items-center">
                    <span class="fw-semibold small flex-grow-1">${name}</span>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-card"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="sets-container"></div>
            </div>
        `;

        card.querySelector('.remove-card').addEventListener('click', () => {
            card.remove();
            if (!routineList.querySelector('.exercise-card')) {
                routineList.innerHTML = `<div class="empty-state"><i class="bi bi-plus-circle"></i><div class="fw-semibold">Drag exercises here</div></div>`;
            }
        });

        const setsContainer = card.querySelector('.sets-container');
        renderSets(setsContainer, cat, eq, 3);
    }

    function renderSets(container, category, equipment, initialCount) {
        container.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mt-2 mb-2">
                <label class="small text-secondary fw-bold">Sets</label>
                <input type="number" class="form-control form-control-sm set-count-input" style="width: 60px;" value="${initialCount}" min="1" max="10">
            </div>
            <div class="set-rows"></div>
            <div class="text-end small text-secondary mt-1">
                <i class="bi bi-fire"></i> ~<span class="cal-total">0</span> kcal
            </div>
        `;

        const rowsContainer = container.querySelector('.set-rows');
        const calSpan = container.querySelector('.cal-total');
        const countInput = container.querySelector('.set-count-input');
        const weightVal = userWeight.value || 75;
        const met = getMetEstimate(category, equipment);

        function drawRows(count) {
            rowsContainer.innerHTML = '';
            for (let i = 1; i <= count; i++) {
                rowsContainer.insertAdjacentHTML('beforeend', `
                    <div class="set-row">
                        <div class="set-num">${i}</div>
                        <input type="number" class="form-control form-control-sm set-weight" placeholder="kg">
                        <input type="text" class="form-control form-control-sm set-reps" placeholder="reps">
                        <input type="number" class="form-control form-control-sm set-rpe" placeholder="RPE" min="1" max="10">
                        <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-set"><i class="bi bi-x"></i></button>
                    </div>
                `);
            }
            bindRowEvents();
            recalc();
        }

        function bindRowEvents() {
            rowsContainer.querySelectorAll('.remove-set').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.target.closest('.set-row').remove();
                    recalc();
                });
            });
        }

        function recalc() {
            const activeRows = rowsContainer.querySelectorAll('.set-row').length;
            countInput.value = activeRows;
            
            // Basic MET calorie estimate: MET * kg * hours
            const duration = (activeRows * 1.5) / 60;
            const cals = Math.round(met * weightVal * duration);
            calSpan.textContent = cals;
        }

        countInput.addEventListener('change', (e) => {
            drawRows(Math.max(1, parseInt(e.target.value) || 1));
        });

        drawRows(initialCount);
    }

    function getMetEstimate(category, equipment) {
        category = (category || '').toLowerCase();
        equipment = (equipment || '').toLowerCase();
        if (category.includes('cardio') || category.includes('plyometrics')) return 8.0;
        if (category.includes('yoga') || category.includes('stretching')) return 3.0;
        if (category.includes('pilates')) return 3.5;
        if (category.includes('strength')) return equipment.includes('body') ? 4.0 : 6.0;
        return 5.0;
    }

    function showStatus(msg, type) {
        if (!msg) { statusEl.innerHTML = ''; return; }
        const cls  = type === 'ok' ? 'success' : 'danger';
        statusEl.innerHTML = `<div class="alert alert-${cls} alert-dismissible fade show mt-3 py-2" role="alert">
            <span class="small">${esc(msg)}</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
        setTimeout(() => statusEl.innerHTML = '', 3500);
    }

    const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
})();