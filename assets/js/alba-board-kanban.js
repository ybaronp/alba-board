// assets/js/alba-board-kanban.js

document.addEventListener('DOMContentLoaded', () => {
  // --- DRAG & DROP LOGIC (Sortable.js) ---
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
          params.append('nonce', albaBoard.nonce);
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
                alert(albaBoard.move_error || 'Could not move the card.');
              }
            })
            .catch(() => {
              alert(albaBoard.move_error || 'Could not move the card.');
            });
        }
      }
    });
  });

  // --- CARD DETAILS MODAL LOGIC ---
  document.querySelectorAll('.alba-card').forEach(card => {
    card.addEventListener('click', function() {
      const cardId = card.dataset.cardId;
      const modal = document.getElementById('alba-card-modal');
      const modalBody = document.getElementById('alba-modal-body');

      if (!cardId || !modal || !modalBody) return;

      // Show modal
      modal.style.display = 'flex';
      modal.classList.add("active");
      modalBody.innerHTML = albaBoard.loading || 'Loading...';
      document.body.style.overflow = "hidden";

      // Load card details via AJAX with security nonce
      fetch(
        albaBoard.ajaxurl +
        '?action=alba_get_card_details' +
        '&card_id=' + encodeURIComponent(cardId) +
        '&nonce=' + encodeURIComponent(albaBoard.get_card_details_nonce)
      )
      .then(res => res.text())
      .then(html => {
        modalBody.innerHTML = html;
        setupDeleteButton(cardId); // Always call after filling modal
        setupCommentHandler(cardId); // If you use inline comments
      })
      .catch(() => {
        modalBody.innerHTML = '<div style="color:red;">Failed to load card details.</div>';
      });
    });
  });

  // --- DELETE HANDLER ---
  function setupDeleteButton(cardId) {
    // Remove all previous handlers to avoid stacking
    const deleteBtn = document.getElementById('alba-modal-delete');
    if (deleteBtn) {
      // Remove previous event listeners
      deleteBtn.onclick = null;
      deleteBtn.addEventListener('click', function() {
        if (!window.AlbaBoardI18n || !confirm(AlbaBoardI18n.confirm_delete || "Are you sure you want to delete this card?")) return;
        const data = new URLSearchParams();
        data.append("action", "alba_delete_card");
        data.append("card_id", cardId);
        data.append("nonce", albaBoardFrontend.nonce);
        fetch(albaBoardFrontend.ajaxurl, {
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

  // --- ADD COMMENT HANDLER ---
  function setupCommentHandler(cardId) {
    const addBtn = document.getElementById("alba-add-comment-btn");
    const textarea = document.getElementById("alba-new-comment-text");
    const feedback = document.getElementById("alba-comment-feedback");
    if (addBtn && textarea) {
      addBtn.onclick = null;
      addBtn.addEventListener('click', function() {
        const comment = textarea.value.trim();
        if (!comment) return;
        feedback.textContent = "";
        addBtn.disabled = true;
        fetch(albaBoardFrontend.ajaxurl, {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: new URLSearchParams({
            action: "alba_add_comment",
            nonce: albaBoardFrontend.nonce,
            card_id: cardId,
            comment: comment
          })
        })
        .then(res => res.json())
        .then(response => {
          addBtn.disabled = false;
          if (response.success) {
            feedback.textContent = "Comment added!";
            textarea.value = "";
            // Reload modal content to refresh comments and button
            fetch(
              albaBoard.ajaxurl +
              '?action=alba_get_card_details' +
              '&card_id=' + encodeURIComponent(cardId) +
              '&nonce=' + encodeURIComponent(albaBoard.get_card_details_nonce)
            )
            .then(res => res.text())
            .then(html => {
              const modalBody = document.getElementById('alba-modal-body');
              modalBody.innerHTML = html;
              setupDeleteButton(cardId); // Reattach after DOM update
              setupCommentHandler(cardId); // Reattach comment handler
            });
          } else {
            feedback.textContent = response.data?.message || "Failed to add comment.";
          }
        });
      });
    }
  }

  // --- CLOSE MODAL LOGIC ---
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