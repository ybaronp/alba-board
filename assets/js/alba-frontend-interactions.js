/** Free frontend creation, comments, and recoverable deletion. */
document.addEventListener('DOMContentLoaded', () => {
  if (window.albaCoreInteractionsInitialized) return;
  window.albaCoreInteractionsInitialized = true;
  const config = window.albaBoardFrontend;
  if (!config) return;
  const labels = config.strings;
  async function request(action, fields) {
    const response = await window.albaBoardRequest.fetch(config.ajaxurl, {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams({action, nonce: config.nonce, ...fields})
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.data?.message || labels.error);
    return result.data;
  }
  function button(text, className) {
    const element = document.createElement('button');
    element.type = 'button'; element.textContent = text; element.className = className;
    return element;
  }
  if (config.can_create) document.querySelectorAll('.alba-cards[data-can-create="1"]').forEach(container => {
    const add = button(labels.newCard, 'alba-add-card-btn alba-mt-10');
    const input = document.createElement('input');
    input.type = 'text'; input.placeholder = labels.title; input.setAttribute('aria-label', labels.title);
    input.className = 'alba-add-card-input alba-form-input-text alba-mt-6 alba-w-100';
    const submit = button(labels.create, 'alba-add-card-submit alba-btn-neumorphic alba-mt-8');
    const feedback = document.createElement('div');
    feedback.className = 'alba-card-feedback alba-text-danger'; feedback.setAttribute('role', 'status');
    input.style.display = submit.style.display = 'none';
    add.addEventListener('click', () => {
      input.style.display = 'block'; submit.style.display = 'inline-block'; feedback.textContent = ''; input.focus();
    });
    submit.addEventListener('click', async () => {
      if (submit.dataset.loading === 'true' || !input.value.trim()) return;
      const title = input.value.trim();
      submit.dataset.loading = 'true'; submit.disabled = true; input.disabled = true;
      try {
        const result = await request('alba_create_card', {title, list_id: container.dataset.listId});
        const card = document.createElement('div');
        card.className = 'alba-card'; card.dataset.cardId = result.card_id; card.dataset.author = result.author_id;
        const heading = document.createElement('span'); heading.className = 'alba-card-title'; heading.textContent = title;
        const footer = document.createElement('div'); footer.className = 'alba-card-footer alba-footer-flex';
        card.append(heading, footer); container.appendChild(card);
        input.value = ''; input.style.display = submit.style.display = 'none'; feedback.textContent = '';
        const toggle = document.getElementById('alba-filter-my-tasks');
        if (toggle?.checked) toggle.dispatchEvent(new Event('change'));
      } catch (error) { feedback.textContent = error.message || labels.error; }
      finally { submit.dataset.loading = 'false'; submit.disabled = false; input.disabled = false; }
    });
    container.parentNode.append(add, input, submit, feedback);
  });
  const modal = document.getElementById('alba-card-modal') || document.getElementById('alba-card-modal-admin');
  if (!modal) return;
  modal.addEventListener('click', async event => {
    const commentButton = event.target.closest('#alba-add-comment-btn');
    const deleteButton = event.target.closest('#alba-modal-delete');
    const control = commentButton || deleteButton;
    if (!control) return;
    event.preventDefault();
    if (control.dataset.loading === 'true') return;
    const textarea = modal.querySelector('#alba-new-comment-text');
    const feedback = modal.querySelector('#alba-comment-feedback');
    const id = deleteButton ? deleteButton.dataset.cardId : textarea?.dataset.cardId;
    if (!id) return;
    if (commentButton && !textarea.value.trim()) {
      if (feedback) feedback.textContent = labels.commentRequired;
      return;
    }
    if (deleteButton && !window.confirm(labels.confirmDelete)) return;
    control.dataset.loading = 'true'; control.disabled = true;
    if (commentButton) window.albaDraftGuard?.begin(modal);
    let commentSaved = false;
    try {
      const data = await request(deleteButton ? 'alba_delete_card' : 'alba_add_card_comment', {
        card_id: id, ...(commentButton ? {comment: textarea.value.trim()} : {})
      });
      if (commentButton) {
        const list = modal.querySelector('#alba-comments-list');
        if (list) {
          list.replaceChildren();
          (data.comments || []).forEach((comment, index) => {
            const row = document.createElement('div'); row.className = 'alba-board-comment'; row.dataset.commentIndex = index;
            const author = document.createElement('strong'); author.textContent = comment.author;
            const authorLabel = document.createElement('span'); authorLabel.className = 'alba-comment-author'; authorLabel.appendChild(author);
            const date = document.createElement('span'); date.className = 'alba-comment-date'; date.textContent = comment.date;
            const text = document.createElement('div'); text.className = 'alba-comment-text';
            if (typeof comment.html === 'string') text.innerHTML = comment.html; // Server-rendered, sanitized Markdown.
            else text.textContent = comment.text;
            row.append(authorLabel, date, text); list.appendChild(row);
          });
          list.scrollTop = list.scrollHeight;
        }
        textarea.value = ''; commentSaved = true; if (feedback) feedback.textContent = '';
      } else {
        document.querySelectorAll('.alba-card').forEach(card => {if (card.dataset.cardId === String(id)) card.remove();});
        document.getElementById('alba-modal-close')?.click();
      }
    } catch (error) {
      if (feedback) feedback.textContent = error.message || labels.error;
      else window.alert(error.message || labels.error);
    } finally { if (commentButton) window.albaDraftGuard?.end(modal, commentSaved); control.dataset.loading = 'false'; control.disabled = false; }
  });
});
