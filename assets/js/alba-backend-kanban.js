/**
 * assets/js/alba-backend-kanban.js
 */

document.addEventListener('DOMContentLoaded', () => {
    
    // --- 1. SORTABLE CARDS ---
    document.querySelectorAll('.alba-cards-container').forEach(list => {
        if (typeof Sortable !== "undefined") {
            new Sortable(list, {
                group: 'alba-cards',
                animation: 150,
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
                filter: '.alba-no-cards-msg',

                onEnd: function (evt) {
                    const card = evt.item;
                    const cardId = card.dataset.cardId;
                    const newListId = evt.to.dataset.listId;
                    const orderedCardIds = Array.from(evt.to.querySelectorAll('.alba-card')).map(el => el.dataset.cardId);

                    checkEmptyStates(); 

                    if (cardId && newListId) {
                        const params = new URLSearchParams();
                        params.append('action', 'alba_move_card');
                        params.append('card_id', cardId);
                        params.append('new_list_id', newListId);
                        params.append('nonce', albaBoard.nonce);

                        orderedCardIds.forEach((id, index) => {
                            params.append(`order[${index}]`, id);
                        });

                        fetch(albaBoard.ajaxurl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: params
                        })
                        .then(res => res.json())
                        .then(response => {
                            if (!response.success && response.data && response.data.message) {
                                alert(response.data.message);
                            }
                        });
                    }
                }
            });
        }
    });

    // --- 2. SORTABLE LISTS ---
    const boardWrapper = document.querySelector('.alba-board-wrapper');
    if (boardWrapper && typeof Sortable !== "undefined") {
        new Sortable(boardWrapper, {
            group: 'alba-lists-group',
            animation: 150,
            draggable: '.alba-list-scrollable', 
            handle: '.alba-list-header',        
            filter: '.alba-delete-list-btn, .alba-list-collapse-btn',
            ghostClass: 'sortable-ghost',
            onEnd: function (evt) {
                const orderedListIds = Array.from(boardWrapper.querySelectorAll('.alba-list-scrollable')).map(el => el.dataset.listId);
                const params = new URLSearchParams();
                params.append('action', 'alba_move_list_action');
                params.append('nonce', albaBoard.move_list_nonce);
                orderedListIds.forEach((id, index) => { params.append(`order[${index}]`, id); });
                fetch(albaBoard.ajaxurl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params });
            }
        });
    }

    // --- 3. REAL-TIME FILTER ENGINE ---
    const searchInput = document.getElementById('alba-filter-search');
    const userFilter  = document.getElementById('alba-filter-user');
    const tagFilter   = document.getElementById('alba-filter-tag');

    function applyFilters() {
        const searchVal = searchInput ? searchInput.value.toLowerCase() : '';
        const userVal   = userFilter ? userFilter.value : '';
        const tagVal    = tagFilter ? tagFilter.value.toLowerCase() : '';

        document.querySelectorAll('.alba-card').forEach(card => {
            let show = true;
            
            const titleEl = card.querySelector('.alba-card-title');
            const title = titleEl ? titleEl.textContent.toLowerCase() : '';
            
            const author = card.dataset.author || '';
            const tags = Array.from(card.querySelectorAll('.alba-card-tag-chip')).map(t => t.textContent.toLowerCase().trim());

            if (searchVal && title.indexOf(searchVal) === -1) show = false;
            if (userVal && author !== userVal) show = false;
            if (tagVal && tags.indexOf(tagVal) === -1) show = false;

            if (show) {
                card.classList.remove('alba-is-hidden-by-filter');
                card.style.display = '';
            } else {
                card.classList.add('alba-is-hidden-by-filter');
                card.style.display = 'none';
            }
        });

        checkEmptyStates();
    }

    function checkEmptyStates() {
        document.querySelectorAll('.alba-cards-container').forEach(listContainer => {
            const visibleCards = listContainer.querySelectorAll('.alba-card:not(.alba-is-hidden-by-filter)').length;
            const noCardsMsg = listContainer.querySelector('.alba-no-cards-msg');
            if (noCardsMsg) {
                if (visibleCards > 0) {
                    noCardsMsg.classList.add('alba-is-hidden');
                } else {
                    noCardsMsg.classList.remove('alba-is-hidden');
                }
            }
        });
    }

    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (userFilter)  userFilter.addEventListener('change', applyFilters);
    if (tagFilter)   tagFilter.addEventListener('change', applyFilters);

    // --- 4. UI INTERACTION (Forms & Buttons) ---
    const boardSelector = document.querySelector('.alba-auto-submit-select');
    if(boardSelector) boardSelector.addEventListener('change', function() { this.form.submit(); });

    document.addEventListener('click', function(e) {
        if (e.target.id === 'alba-show-new-board-btn') {
            e.preventDefault();
            const form = document.getElementById('alba-new-board-form');
            if(form) { form.classList.remove('alba-is-hidden'); e.target.classList.add('alba-is-hidden'); const input = form.querySelector('input[type="text"]'); if (input) input.focus(); }
        }
        if (e.target.id === 'alba-cancel-new-board-btn') {
            e.preventDefault();
            const form = document.getElementById('alba-new-board-form'); const btn = document.getElementById('alba-show-new-board-btn');
            if(form && btn) { form.classList.add('alba-is-hidden'); btn.classList.remove('alba-is-hidden'); }
        }

        const showListBtn = e.target.closest('.alba-show-add-list-btn');
        if (showListBtn) {
            e.preventDefault(); const form = showListBtn.nextElementSibling;
            if(form) { form.classList.remove('alba-is-hidden'); showListBtn.classList.add('alba-is-hidden'); const input = form.querySelector('input[type="text"]'); if (input) input.focus(); }
        }
        const cancelListBtn = e.target.closest('.alba-cancel-new-list-btn');
        if (cancelListBtn) {
            e.preventDefault(); const form = cancelListBtn.closest('form');
            if(form) { form.classList.add('alba-is-hidden'); if(form.previousElementSibling) form.previousElementSibling.classList.remove('alba-is-hidden'); }
        }

        const showCardBtn = e.target.closest('.alba-show-add-card-btn');
        if (showCardBtn) {
            e.preventDefault(); const form = showCardBtn.nextElementSibling;
            if(form) { form.classList.remove('alba-is-hidden'); showCardBtn.classList.add('alba-is-hidden'); const input = form.querySelector('input[type="text"]'); if (input) input.focus(); }
        }
        const cancelCardBtn = e.target.closest('.alba-cancel-new-card-btn');
        if (cancelCardBtn) {
            e.preventDefault(); const form = cancelCardBtn.closest('form');
            if(form) { form.classList.add('alba-is-hidden'); if(form.previousElementSibling) form.previousElementSibling.classList.remove('alba-is-hidden'); }
        }

        const deleteListBtn = e.target.closest('.alba-delete-list-btn');
        if (deleteListBtn) {
            e.preventDefault();
            if (confirm('Are you sure you want to delete this list and ALL its cards?')) {
                const listId = deleteListBtn.dataset.listId;
                const listContainer = deleteListBtn.closest('.alba-list');
                deleteListBtn.style.opacity = '0.5'; deleteListBtn.style.pointerEvents = 'none';
                
                const formData = new URLSearchParams();
                formData.append('action', 'alba_delete_list_action'); 
                formData.append('list_id', listId); 
                formData.append('nonce', albaBoard.delete_list_nonce);
                
                fetch(albaBoard.ajaxurl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: formData })
                .then(res => res.json())
                .then(response => {
                    if (response.success) {
                        listContainer.style.transition = 'all 0.3s ease'; listContainer.style.opacity = '0'; listContainer.style.transform = 'scale(0.9)';
                        setTimeout(() => listContainer.remove(), 300);
                    } else { 
                        const errMsg = (response.data && response.data.message) ? response.data.message : 'Error deleting list.';
                        alert(errMsg); 
                        deleteListBtn.style.opacity = '1'; 
                        deleteListBtn.style.pointerEvents = 'auto'; 
                    }
                });
            }
        }
    });

    // --- 5. MODAL LOGIC (REST API Bypass) ---
    const modalAdmin = document.getElementById('alba-card-modal-admin');
    const modalBody = document.getElementById('alba-modal-body-admin');
    let currentOpenedCard = null;

    document.querySelectorAll('.alba-card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (e.target.closest('.alba-card-action-btn') || e.target.closest('a') || e.target.closest('button')) return;
            const cardId = card.dataset.cardId; currentOpenedCard = card;
            
            if(modalAdmin) { modalAdmin.classList.remove('alba-is-hidden'); modalAdmin.classList.add('active'); }
            if(modalBody) modalBody.innerHTML = albaBoard.loading || 'Loading...';

            fetch(albaBoard.rest_url + 'alba-board/v1/card/' + cardId + '?context=admin', {
                method: 'GET',
                headers: { 'X-WP-Nonce': albaBoard.rest_nonce }
            })
            .then(res => res.json())
            .then(response => {
                if(modalBody && response.html) {
                    modalBody.innerHTML = response.html;
                    bindSaveButtonHandler(); 
                    bindAttachmentHandlers(); 
                    jQuery(document).trigger('alba_modal_loaded');
                }
            })
            .catch(() => { 
                if(modalBody) modalBody.innerHTML = albaBoard.fetch_error || 'Error loading card.'; 
            });
        });
    });

    const closeModal = () => { if(modalAdmin) { modalAdmin.classList.add('alba-is-hidden'); modalAdmin.classList.remove('active'); } currentOpenedCard = null; };
    
    const closeBtn = document.getElementById('alba-modal-close-admin');
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    
    window.addEventListener('click', (e) => { if (e.target.id === 'alba-card-modal-admin') closeModal(); });

    document.addEventListener('keydown', (e) => {
        const isModalOpen = modalAdmin && !modalAdmin.classList.contains('alba-is-hidden');
        if (e.key === 'Escape') {
            if (isModalOpen) { closeModal(); return; }
            document.querySelectorAll('.alba-stacked-form:not(.alba-is-hidden), .alba-inline-form:not(.alba-is-hidden)').forEach(form => {
                form.classList.add('alba-is-hidden');
                if (form.previousElementSibling && form.previousElementSibling.tagName === 'BUTTON') form.previousElementSibling.classList.remove('alba-is-hidden');
            });
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            if (isModalOpen) { e.preventDefault(); const saveBtn = document.getElementById('alba-card-save-btn'); if (saveBtn) { saveBtn.focus(); saveBtn.click(); } }
        }
    });

    // --- 6. FLATPICKR INITIALIZATION ---
    jQuery(document).on('alba_modal_loaded', function() {
        const dateInput = document.getElementById('alba-card-due-date');
        if (dateInput && typeof flatpickr !== 'undefined') {
            const fp = flatpickr(dateInput, {
                dateFormat: "Y-m-d",
                disableMobile: true 
            });
            
            const clearBtn = document.getElementById('alba-clear-date');
            if (clearBtn) {
                clearBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    fp.clear();
                    dateInput.value = '';
                    this.style.display = 'none';
                });
            }
        }
    });

    // --- 7. SAVE HANDLER ---
    function bindSaveButtonHandler() {
        const saveBtn = document.getElementById('alba-card-save-btn');
        if (!saveBtn) return;
        saveBtn.onclick = function (e) {
            e.preventDefault(); 
            const form = document.getElementById('alba-card-details-form'); 
            if (!form) return;
            
            const formData = new FormData(form);
            formData.append('action', 'alba_save_card_details_admin'); 
            formData.append('nonce', albaBoard.save_card_details_nonce);
            
            const newCommentField = document.getElementById('alba-new-comment');
            if (newCommentField) formData.set('new_comment', newCommentField.value);
            
            const dueDateInput = document.getElementById('alba-card-due-date');
            if (dueDateInput) {
                formData.set('due_date', dueDateInput.value);
            }

            saveBtn.textContent = 'Saving...'; saveBtn.style.opacity = '0.7';
            
            fetch(albaBoard.ajaxurl, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(response => {
                if (response && response.success) { 
                    location.reload(); 
                } else { 
                    saveBtn.textContent = 'Save Changes'; saveBtn.style.opacity = '1'; 
                }
            });
        }
    }

    // --- 8. ATTACHMENT HANDLERS (PURIFIED API) ---
    function bindAttachmentHandlers() {
        const fileInput = document.getElementById('alba-file-upload-input');
        const triggerBtn = document.getElementById('alba-trigger-upload-btn');
        const feedbackDiv = document.getElementById('alba-upload-feedback');
        const cardIdInput = document.getElementById('alba-current-card-id');

        if (triggerBtn && fileInput) {
            triggerBtn.onclick = (e) => {
                e.preventDefault();
                fileInput.value = ''; // Clear before opening dialog to allow same-file selection
                fileInput.click();
            };
        }
        
        if (fileInput) {
            fileInput.onchange = function() {
                const files = this.files;
                if (!files || files.length === 0) return;
                
                const fileObj = files; 
                if (!cardIdInput || !cardIdInput.value) return;
                
                feedbackDiv.textContent = albaBoard.uploading || 'Uploading...'; 
                feedbackDiv.style.color = '#2271b1';
                
                const formData = new FormData();
                formData.append('action', 'alba_upload_attachment'); 
                formData.append('card_id', cardIdInput.value); 
                formData.append('nonce', albaBoard.upload_attachment_nonce); 
                
                // Standard File object appended. Fetch will generate multipart headers correctly.
                formData.append('file', fileObj); 
                
                fetch(albaBoard.ajaxurl, { 
                    method: 'POST', 
                    body: formData 
                })
                .then(res => res.json())
                .then(response => {
                    if (response.success) {
                        feedbackDiv.textContent = '';
                        const listDiv = document.getElementById('alba-attachments-list');
                        
                        const noMsg = document.getElementById('alba-no-attachments-msg'); 
                        if (noMsg) noMsg.remove();
                        
                        const newItem = document.createElement('div');
                        newItem.className = 'alba-attachment-item'; 
                        newItem.id = 'alba-attachment-' + response.data.attachment_id;
                        newItem.style = 'display: flex; justify-content: space-between; align-items: center; background: var(--alba-card-bg); padding: 8px 14px; border-radius: 12px; box-shadow: 2px 2px 6px var(--alba-shadow-dark), -2px -2px 6px var(--alba-shadow-light); margin-top: 8px;';
                        newItem.innerHTML = `<a href="${response.data.file_url}" target="_blank" style="text-decoration: none; color: var(--alba-text-main); font-weight: 600; font-size: 0.95em;">📄 ${response.data.file_name}</a><button type="button" class="alba-delete-attachment-btn" data-attachment-id="${response.data.attachment_id}" style="background: none; border: none; color: var(--alba-danger); cursor: pointer; font-size: 1.2em; outline: none;">&times;</button>`;
                        
                        listDiv.appendChild(newItem); 
                        bindDeleteButtons();
                    } else { 
                        const errMsg = (response.data && response.data.message) ? response.data.message : 'Error uploading.';
                        feedbackDiv.textContent = errMsg; 
                        feedbackDiv.style.color = 'var(--alba-danger)'; 
                    }
                })
                .catch(() => {
                    feedbackDiv.textContent = 'Server connection failed.';
                    feedbackDiv.style.color = 'var(--alba-danger)';
                })
                .finally(() => {
                    fileInput.value = ''; // Clean up after promise resolves
                });
            };
        }
        bindDeleteButtons();
    }

    function bindDeleteButtons() {
        document.querySelectorAll('.alba-delete-attachment-btn').forEach(btn => {
            btn.onclick = function(e) {
                e.preventDefault(); 
                const attachmentId = this.dataset.attachmentId; 
                const cardIdInput = document.getElementById('alba-current-card-id'); 
                const itemDiv = document.getElementById('alba-attachment-' + attachmentId);
                
                if (!attachmentId || !cardIdInput) return;
                
                this.textContent = '...'; 
                this.disabled = true;
                
                const formData = new FormData();
                formData.append('action', 'alba_delete_attachment'); 
                formData.append('card_id', cardIdInput.value); 
                formData.append('attachment_id', attachmentId); 
                formData.append('nonce', albaBoard.delete_attachment_nonce);
                
                fetch(albaBoard.ajaxurl, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(response => {
                    if (response.success && itemDiv) { 
                        itemDiv.remove(); 
                    } else { 
                        const errMsg = (response.data && response.data.message) ? response.data.message : 'Failed to delete.';
                        alert(errMsg); 
                        this.textContent = '✖'; 
                        this.disabled = false; 
                    }
                });
            };
        });
    }

    // --- 9. Select2 Initialization for Authors ---
    jQuery(document).on('DOMNodeInserted', function(e) {
        jQuery(e.target).find('.alba-select2:not(.alba-tags-select2)').each(function() {
            if (!jQuery(this).hasClass('select2-hidden-accessible')) {
                jQuery(this).select2({
                    width: '100%',
                    dropdownParent: jQuery('#alba-card-modal-admin .alba-modal-content')
                });
            }
        });
    });

    // --- 10. LIST COLLAPSE LOGIC (With LocalStorage Persistence) ---
    function initListCollapse() {
        const storageKey = 'alba_collapsed_lists';
        let collapsedLists = JSON.parse(localStorage.getItem(storageKey)) || [];

        // Apply collapsed state on load
        collapsedLists.forEach(listId => {
            const listContainer = document.querySelector(`.alba-cards[data-list-id="${listId}"], .alba-cards-container[data-list-id="${listId}"]`);
            if (listContainer) {
                const wrapper = listContainer.closest('.alba-list-column, .alba-list');
                if (wrapper) wrapper.classList.add('alba-list-collapsed');
            }
        });

        // Toggle click handler
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.alba-list-collapse-btn');
            if (!btn) return;
            e.preventDefault();

            const wrapper = btn.closest('.alba-list-column, .alba-list');
            if (!wrapper) return;

            const listContainer = wrapper.querySelector('.alba-cards, .alba-cards-container');
            const listId = listContainer ? listContainer.dataset.listId : null;

            if (listId) {
                wrapper.classList.toggle('alba-list-collapsed');
                
                if (wrapper.classList.contains('alba-list-collapsed')) {
                    if (!collapsedLists.includes(listId)) collapsedLists.push(listId);
                } else {
                    collapsedLists = collapsedLists.filter(id => id !== listId);
                }
                
                localStorage.setItem(storageKey, JSON.stringify(collapsedLists));
            }
        });
    }
    
    // Initialize the collapse logic
    initListCollapse();
});