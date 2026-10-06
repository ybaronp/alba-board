/**
 * assets/js/alba-board-frontend.js
 */

document.addEventListener('DOMContentLoaded', () => {
  if (window.albaFrontendKanbanInitialized) return;
  window.albaFrontendKanbanInitialized = true;

  // Add-on creation controls must not also submit a surrounding page form.
  document.addEventListener('click', (e) => {
    const button = e.target.closest('.alba-add-card-btn, .alba-add-card-submit');
    if (!button) return;
    button.type = 'button';
    e.preventDefault();
    if (button.dataset.loading === 'true') {
      e.stopImmediatePropagation();
    }
  }, true);

  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' || !e.target.matches('.alba-add-card-input')) return;
    e.preventDefault();
    if (e.repeat || e.isComposing) return;
    const list = e.target.closest('.alba-list-column');
    const button = list && list.querySelector('.alba-add-card-submit');
    if (button && !button.disabled && button.dataset.loading !== 'true') button.click();
  });

  // Helper functions for clean URL mapping via History API
  function updateUrlWithCard(id) {
    const url = new URL(window.location);
    url.searchParams.set('alba_card', id);
    window.history.pushState({ path: url.href }, '', url.href);
  }
  function removeCardFromUrl() {
    const url = new URL(window.location);
    url.searchParams.delete('alba_card');
    window.history.replaceState({ path: url.href }, '', url.href);
  }

  // --- 1. DRAG & DROP (Sortable.js) ---
  const lists = document.querySelectorAll('.alba-cards');
  const originalCardLocations = new WeakMap();
  if (albaBoard.can_move_cards && typeof Sortable === 'undefined') {
    console.error('Sortable.js not loaded');
  }

  if (albaBoard.can_move_cards && typeof Sortable !== 'undefined') lists.forEach(list => {
    new Sortable(list, {
      group: 'alba-cards',
      animation: 150,
      ghostClass: 'sortable-ghost',
      dragClass: 'sortable-drag',
      onStart: function (evt) {
        originalCardLocations.set(evt.item, {
          parent: evt.from,
          nextSibling: evt.item.nextSibling
        });
      },
      onEnd: function (evt) {
        const card = evt.item;
        const cardId = card.dataset.cardId;
        const newListId = evt.to.dataset.listId;

        const orderedCardIds = Array.from(evt.to.querySelectorAll('.alba-card'))
          .map(el => el.dataset.cardId);

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
              if (!response.success) {
                const originalLocation = originalCardLocations.get(card);
                if (originalLocation && originalLocation.parent) {
                  originalLocation.parent.insertBefore(card, originalLocation.nextSibling && originalLocation.nextSibling.parentNode === originalLocation.parent ? originalLocation.nextSibling : null);
                }
                alert(albaBoard.move_error || 'Could not move the card.');
              }
            })
            .catch(() => {
              const originalLocation = originalCardLocations.get(card);
              if (originalLocation && originalLocation.parent) {
                originalLocation.parent.insertBefore(card, originalLocation.nextSibling && originalLocation.nextSibling.parentNode === originalLocation.parent ? originalLocation.nextSibling : null);
              }
              alert(albaBoard.move_error || 'Could not move the card.');
            });
        }
      }
    });
  });

  // --- 2. CARD DETAILS MODAL (REST API Integration) ---
  document.addEventListener('click', function(e) {
    const card = e.target.closest('.alba-card');
    if (!card || e.target.closest('button') || e.target.closest('a')) return;

    const cardId = card.dataset.cardId;
    const modal = document.getElementById('alba-card-modal');
    const modalBody = document.getElementById('alba-modal-body');

    if (!cardId || !modal || !modalBody) return;

    modal.classList.add("active");
    document.body.classList.add("alba-body-no-scroll");
    
    modalBody.innerHTML = albaBoard.loading || 'Loading...';

    // Keep the selected card in the browser URL.
    updateUrlWithCard(cardId);

    fetch(albaBoard.rest_url + 'alba-board/v1/card/' + encodeURIComponent(cardId) + '?context=frontend', { 
        method: 'GET',
        headers: {
            'X-WP-Nonce': albaBoard.rest_nonce
        }
    })
    .then(res => res.json())
    .then(response => {
        if (response && response.html) {
            modalBody.innerHTML = response.html;
        } else if (response && response.message) {
            modalBody.innerHTML = `<div class="alba-error-msg">${response.message}</div>`;
        }
    })
    .catch(() => {
        modalBody.innerHTML = '<div class="alba-error-msg">Failed to load card details.</div>';
    });
  });

  // --- 3. CLOSE MODAL HANDLERS ---
  const closeModal = () => {
      const modal = document.getElementById('alba-card-modal');
      if (modal) {
          modal.classList.remove("active");
          document.body.classList.remove("alba-body-no-scroll");
          removeCardFromUrl(); // Revert Browser URL natively
      }
  };

  const closeBtn = document.getElementById('alba-modal-close');
  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  
  window.addEventListener('click', (e) => {
    const modal = document.getElementById('alba-card-modal');
    if (modal && e.target === modal) closeModal();
  });

  // --- 4. ACCESSIBILITY: KEYBOARD SHORTCUTS ---
  document.addEventListener('keydown', (e) => {
    const modalFrontend = document.getElementById('alba-card-modal');
    const isModalOpen = modalFrontend && modalFrontend.classList.contains('active');

    if (e.key === 'Escape' && isModalOpen) {
        closeModal();
        return;
    }

    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && isModalOpen) {
        e.preventDefault();
        if (e.repeat || e.isComposing) return;
        const addCommentBtn = document.getElementById('alba-add-comment-btn');
        if (addCommentBtn) {
            addCommentBtn.focus();
            addCommentBtn.click();
        }
    }
  });

  // --- 5. "MY TASKS" FILTER LOGIC (CSS Driven) ---
  const myTasksToggle = document.getElementById('alba-filter-my-tasks');
  
  if (myTasksToggle) {
      myTasksToggle.addEventListener('change', function() {
          const currentUserId = this.dataset.userId;
          const isChecked = this.checked;

          document.querySelectorAll('.alba-card').forEach(card => {
              const cardAuthor = card.dataset.author;
              
              if (isChecked && cardAuthor !== currentUserId) {
                  card.classList.add('alba-card-filtered-out');
              } else {
                  card.classList.remove('alba-card-filtered-out');
              }
          });
      });
  }

  // --- 6. LIST COLLAPSE LOGIC (LocalStorage Persistence) ---
  function initListCollapse() {
      const storageKey = 'alba_collapsed_lists'; 
      let collapsedLists = JSON.parse(localStorage.getItem(storageKey)) || [];

      collapsedLists.forEach(listId => {
          const listContainer = document.querySelector(`.alba-cards[data-list-id="${listId}"]`);
          if (listContainer) {
              const wrapper = listContainer.closest('.alba-list-column');
              if (wrapper) wrapper.classList.add('alba-list-collapsed');
          }
      });

      document.addEventListener('click', (e) => {
          const btn = e.target.closest('.alba-list-collapse-btn');
          if (!btn) return;
          e.preventDefault();

          const wrapper = btn.closest('.alba-list-column');
          if (!wrapper) return;

          const listContainer = wrapper.querySelector('.alba-cards');
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
  
  initListCollapse();
});
