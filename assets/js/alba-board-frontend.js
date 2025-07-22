// assets/js/alba-board-frontend.js

document.addEventListener('DOMContentLoaded', () => {
  // --- DRAG & DROP (Sortable.js) ---
  const lists = document.querySelectorAll('.alba-cards');
  if (typeof Sortable === 'undefined') {
    console.error('Sortable.js not loaded');
    return;
  }

  lists.forEach(list => {
    new Sortable(list, {
      group: 'alba-cards',
      animation: 150,
      ghostClass: 'sortable-ghost',
      dragClass: 'sortable-drag',
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
          params.append('nonce', albaBoard.nonce); // Use the global nonce
          orderedCardIds.forEach((id, index) => {
            params.append(`order[${index}]`, id);
          });

          fetch(albaBoard.ajaxurl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: params
          })
            .then(res => res.json())
            .then(response => {
              if (!response.success) {
                alert(window.AlbaBoardI18n?.move_error || 'Could not move the card.');
              }
            })
            .catch(() => {
              alert(window.AlbaBoardI18n?.move_error || 'Could not move the card.');
            });
        }
      }
    });
  });

  // --- CARD DETAILS MODAL ---
  document.querySelectorAll('.alba-card').forEach(card => {
    card.addEventListener('click', function() {
      const cardId = card.dataset.cardId;
      const modal = document.getElementById('alba-card-modal');
      const modalBody = document.getElementById('alba-modal-body');

      if (!cardId || !modal || !modalBody) return;

      // Show modal
      modal.style.display = 'flex';
      modal.classList.add("active");
      modalBody.innerHTML = window.AlbaBoardI18n?.loading || 'Loading...';
      document.body.style.overflow = "hidden";

      // AJAX for card details
      fetch(
        albaBoard.ajaxurl +
        '?action=alba_get_card_details' +
        '&card_id=' + encodeURIComponent(cardId) +
        '&nonce=' + encodeURIComponent(albaBoard.get_card_details_nonce)
      )
      .then(res => res.text())
      .then(html => {
        modalBody.innerHTML = html;
        setupDeleteButton(cardId);
        // NO comment handler here!
      })
      .catch(() => {
        modalBody.innerHTML = '<div style="color:red;">Failed to load card details.</div>';
      });
    });
  });

  // --- DELETE HANDLER ---
  function setupDeleteButton(cardId) {
    const deleteBtn = document.getElementById('alba-modal-delete');
    if (deleteBtn) {
      deleteBtn.onclick = null;
      deleteBtn.addEventListener('click', function() {
        if (!window.AlbaBoardI18n || !confirm(window.AlbaBoardI18n.confirm_delete || "Are you sure you want to delete this card?")) return;
        const data = new URLSearchParams();
        data.append("action", "alba_delete_card");
        data.append("card_id", cardId);
        data.append("nonce", albaBoard.nonce);
        fetch(albaBoard.ajaxurl, {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: data
        })
        .then(res => res.json())
        .then(response => {
          if (response.success) {
            location.reload();
          } else {
            alert(response.data?.message || response.message || (window.AlbaBoardI18n?.delete_error || "Error deleting card"));
          }
        });
      });
    }
  }

  // --- CLOSE MODAL ---
  const closeBtn = document.getElementById('alba-modal-close');
  if (closeBtn) {
    closeBtn.addEventListener('click', () => {
      const modal = document.getElementById('alba-card-modal');
      if (modal) {
        modal.style.display = 'none';
        modal.classList.remove("active");
        document.body.style.overflow = "";
      }
    });
  }
  window.addEventListener('click', (e) => {
    const modal = document.getElementById('alba-card-modal');
    if (modal && e.target === modal) {
      modal.style.display = 'none';
      modal.classList.remove("active");
      document.body.style.overflow = "";
    }
  });
});