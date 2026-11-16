(() => {
    'use strict';

    const CSRF = document.querySelector('meta[name=csrf]').content;
    const $ = id => document.getElementById(id);

    const exerciseList = $('exercise-list');
    const routineList  = $('routine-list');
    const searchInput  = $('search');
    const bodyPartSel  = $('filter-bodypart');
    const equipmentSel = $('filter-equipment');
    const resultCount  = $('result-count');
    const routineName  = $('routine-name');
    const loadSelect   = $('load-routine');
    const btnSave      = $('btn-save');
    const btnNew       = $('btn-new');
    const btnDelete    = $('btn-delete');
    const statusEl     = $('status');

    let currentRoutineId = null;
    let searchTimer = null;

    /* ============ Catalogue ========================================== */
    async function loadExercises() {
        const params = new URLSearchParams({
            action: 'search',
            q: searchInput.value.trim(),
            bodyPart: bodyPartSel.value,
            equipment: equipmentSel.value,
        });
        try {
            const res = await fetch(`api.php?${params}`);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            
            const data = await res.json();
            resultCount.textContent = `${data.length.toLocaleString()} exercise${data.length === 1 ? '' : 's'}`;

            if (!data.length) {
                exerciseList.innerHTML = `
                    <div class="empty-state">
                        <i class="bi bi-search"></i>
                        <div>No exercises match your filters.</div>
                    </div>`;
                return;
            }

            exerciseList.innerHTML = data.map(exerciseCardHtml).join('');
        } catch (err) {
            exerciseList.innerHTML = `
                <div class="alert alert-danger m-0 small">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    Failed to load exercises: ${esc(err.message)}
                </div>`;
        }
    }

    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadExercises, 220);
    });
    bodyPartSel.addEventListener('change', loadExercises);
    equipmentSel.addEventListener('change', loadExercises);
    loadExercises();

    /* ============ Routine list in select ============================ */
    async function loadRoutineList() {
        try {
            const rows = await (await fetch('api.php?action=routines')).json();
            loadSelect.innerHTML = '<option value="">— New routine —</option>';
            rows.forEach(r => {
                const opt = document.createElement('option');
                opt.value = r.id;
                opt.textContent = `${r.name} (${r.exercise_count})`;
                loadSelect.appendChild(opt);
            });
        } catch(e) { console.error('Failed to load routines list', e); }
    }
    loadRoutineList();

    /* ============ Load / reset a routine ============================= */
    async function loadRoutine(id) {
        if (!id) {
            currentRoutineId = null;
            routineName.value = 'My workout';
            btnDelete.hidden = true;
            showEmptyState();
            return;
        }

        const routine = await (await fetch(`api.php?action=routine&id=${id}`)).json();
        if (!routine || routine.error) {
            showStatus('Could not load routine.', 'error');
            return;
        }

        currentRoutineId = routine.id;
        routineName.value = routine.name;
        btnDelete.hidden = false;

        routineList.innerHTML = routine.exercises.length
            ? routine.exercises.map(ex => routineCardHtml(ex, true)).join('')
            : '';
        if (!routine.exercises.length) showEmptyState();
    }
    loadSelect.addEventListener('change', () => loadRoutine(loadSelect.value));

    const urlLoad = new URLSearchParams(location.search).get('load');
    if (urlLoad) { loadSelect.value = urlLoad; loadRoutine(urlLoad); }

    /* ============ SortableJS ========================================= */
    new Sortable(exerciseList, {
        group: { name: 'exercises', pull: 'clone', put: false },
        sort: false,
        animation: 150,
    });

    new Sortable(routineList, {
        group: { name: 'exercises', pull: false, put: true },
        animation: 150,
        onAdd: evt => {
            routineList.querySelector('.empty-state')?.remove();

            const card = evt.item;
            card.classList.add('mb-2', 'position-relative');
            
            // Build the interactive interior
            const body = card.querySelector('.card-body');
            body.classList.replace('p-2', 'p-3');
            
            body.insertAdjacentHTML('afterbegin', `
                <button type="button" class="btn btn-sm btn-outline-danger remove-card position-absolute top-0 end-0 m-2" title="Remove">
                    <i class="bi bi-x-lg"></i>
                </button>
            `);

            body.insertAdjacentHTML('beforeend', setsBuilderHtml(3));
        },
    });

    /* Remove buttons */
    routineList.addEventListener('click', e => {
        const btn = e.target.closest('.remove-card');
        if (!btn) return;
        btn.closest('.exercise-card').remove();
        if (!routineList.querySelector('.exercise-card')) showEmptyState();
    });

    /* Handle dynamic Sets count change */
    routineList.addEventListener('change', e => {
        if (!e.target.classList.contains('ex-sets-count')) return;
        
        const card = e.target.closest('.exercise-card');
        const setContainer = card.querySelector('.sets-container');
        const newCount = parseInt(e.target.value, 10);
        const currentCount = setContainer.querySelectorAll('.set-row').length;

        if (newCount > currentCount) {
            // Add rows
            for (let i = currentCount; i < newCount; i++) {
                setContainer.insertAdjacentHTML('beforeend', setRowHtml(i + 1));
            }
        } else if (newCount < currentCount) {
            // Remove rows from bottom
            const rows = setContainer.querySelectorAll('.set-row');
            for (let i = currentCount - 1; i >= newCount; i--) {
                rows[i].remove();
            }
        }
    });

    /* ============ Save =============================================== */
    btnSave.addEventListener('click', async () => {
        const name = routineName.value.trim();
        if (!name) return showStatus('Give the routine a name first.', 'error');

        const cards = [...routineList.querySelectorAll('.exercise-card')];
        if (!cards.length) return showStatus('Add at least one exercise.', 'error');

        const exercises = cards.map((card, i) => {
            const sets = [...card.querySelectorAll('.set-row')].map(row => ({
                weight: row.querySelector('.set-weight')?.value || '',
                reps:   row.querySelector('.set-reps')?.value || '',
                rpe:    row.querySelector('.set-rpe')?.value || '',
                rest:   row.querySelector('.set-rest')?.value || ''
            }));

            return {
                id: card.dataset.id,
                position: i,
                sets: sets
            };
        });

        const body = { name, exercises };
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
            await loadRoutineList();
            loadSelect.value = String(currentRoutineId);
        } catch (err) {
            showStatus(err.message || 'Could not save routine.', 'error');
        } finally {
            btnSave.disabled = false;
        }
    });

    /* ============ New / Delete ======================================= */
    btnNew.addEventListener('click', () => {
        loadSelect.value = '';
        loadRoutine('');
        showStatus('', '');
    });

    btnDelete.addEventListener('click', async () => {
        if (currentRoutineId === null) return;
        if (!confirm('Delete this routine permanently?')) return;

        btnDelete.disabled = true;
        try {
            await fetch(`api.php?action=delete-routine&id=${currentRoutineId}`, {
                method: 'POST',
                headers: { 'X-CSRF-Token': CSRF },
            });
            currentRoutineId = null;
            btnDelete.hidden = true;
            loadSelect.value = '';
            routineName.value = 'My workout';
            showEmptyState();
            await loadRoutineList();
            showStatus('Routine deleted.', 'ok');
        } catch {
            showStatus('Could not delete routine.', 'error');
        } finally {
            btnDelete.disabled = false;
        }
    });

    /* ============ Helpers ============================================ */
    function exerciseCardHtml(ex) {
        return `
            <div class="exercise-card card border-secondary-subtle bg-body-tertiary mb-2" data-id="${ex.id}">
                <div class="card-body p-2">
                    <div class="fw-semibold small">${esc(ex.name)}</div>
                    <div class="d-flex flex-wrap gap-1 mt-1">
                        ${ex.body_part ? `<span class="badge bg-primary-subtle text-primary-emphasis fw-normal">${esc(ex.body_part)}</span>` : ''}
                        ${ex.equipment ? `<span class="badge bg-success-subtle text-success-emphasis fw-normal">${esc(ex.equipment)}</span>` : ''}
                    </div>
                </div>
            </div>`;
    }

    function routineCardHtml(ex, withInputs = false) {
        return `
            <div class="exercise-card card border-secondary-subtle bg-body-tertiary mb-2 position-relative" data-id="${ex.id}">
                <div class="card-body p-3">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-card position-absolute top-0 end-0 m-2" title="Remove">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <div class="fw-semibold pe-5 mb-1">${esc(ex.name)}</div>
                    <div class="d-flex flex-wrap gap-1 mb-3">
                        ${ex.body_part ? `<span class="badge bg-primary-subtle text-primary-emphasis fw-normal">${esc(ex.body_part)}</span>` : ''}
                        ${ex.equipment ? `<span class="badge bg-success-subtle text-success-emphasis fw-normal">${esc(ex.equipment)}</span>` : ''}
                    </div>
                    ${withInputs ? setsBuilderHtml(ex.sets?.length || 3, ex.sets) : ''}
                </div>
            </div>`;
    }

    function setsBuilderHtml(numSets = 3, existingSets = null) {
        let rows = '';
        for (let i = 0; i < numSets; i++) {
            const s = existingSets && existingSets[i] ? existingSets[i] : null;
            rows += setRowHtml(i + 1, s);
        }

        return `
            <div class="sets-wrapper bg-body rounded p-2 border border-secondary-subtle">
                <div class="d-flex align-items-center gap-2 mb-2 pb-2 border-bottom border-secondary-subtle">
                    <label class="small text-secondary fw-semibold mb-0">Sets:</label>
                    <select class="form-select form-select-sm ex-sets-count" style="width: 70px;">
                        ${[1,2,3,4,5,6,7,8].map(n => `<option value="${n}" ${n === numSets ? 'selected' : ''}>${n}</option>`).join('')}
                    </select>
                </div>
                <div class="sets-container">
                    ${rows}
                </div>
            </div>
        `;
    }

    function setRowHtml(setNum, data = null) {
        const weight = data ? data.weight : '';
        const reps   = data ? data.reps : '';
        const rpe    = data ? data.rpe : '';
        const rest   = data ? data.rest : '90';

        let rpeOptions = '<option value="">-</option>';
        for (let i = 1; i <= 10; i++) {
            rpeOptions += `<option value="${i}" ${rpe == i ? 'selected' : ''}>${i}</option>`;
        }

        return `
            <div class="set-row d-flex flex-wrap gap-2 align-items-end mb-2">
                <div class="fw-bold text-secondary small" style="width: 20px; padding-bottom: 6px;">#${setNum}</div>
                
                <div style="flex: 1; min-width: 60px;">
                    <label class="text-secondary" style="font-size: 0.7rem;">Weight</label>
                    <input type="number" class="form-control form-control-sm set-weight" value="${weight}" placeholder="kg/lbs">
                </div>

                <div style="flex: 1; min-width: 60px;">
                    <label class="text-secondary" style="font-size: 0.7rem;">Reps</label>
                    <input type="number" class="form-control form-control-sm set-reps" value="${reps}" placeholder="0">
                </div>

                <div style="flex: 1; min-width: 60px;">
                    <label class="text-secondary" style="font-size: 0.7rem;">RPE</label>
                    <select class="form-select form-select-sm set-rpe">
                        ${rpeOptions}
                    </select>
                </div>

                <div style="flex: 1; min-width: 60px;">
                    <label class="text-secondary" style="font-size: 0.7rem;">Rest(s)</label>
                    <input type="number" class="form-control form-control-sm set-rest" value="${rest}">
                </div>
            </div>
        `;
    }

    function showEmptyState() {
        routineList.innerHTML = `
            <div class="empty-state">
                <i class="bi bi-plus-circle"></i>
                <div class="fw-semibold">Drag exercises here</div>
                <div class="small">Pick from the catalogue on the left</div>
            </div>`;
    }

    function showStatus(msg, type) {
        if (!msg) { statusEl.innerHTML = ''; return; }
        const cls  = type === 'ok' ? 'success' : type === 'error' ? 'danger' : 'info';
        const icon = type === 'ok' ? 'check-circle-fill' : type === 'error' ? 'exclamation-triangle-fill' : 'info-circle-fill';

        statusEl.innerHTML = `
            <div class="alert alert-${cls} alert-dismissible fade show d-flex align-items-center gap-2 mb-0 mt-3 py-2 px-3" role="alert">
                <i class="bi bi-${icon}"></i>
                <span class="small">${esc(msg)}</span>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`;

        setTimeout(() => {
            const a = statusEl.querySelector('.alert');
            if (a) { a.classList.remove('show'); setTimeout(() => statusEl.innerHTML = '', 150); }
        }, 3500);
    }

    const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
})();