# Alba Board

Alba Board is a powerful Kanban-style board plugin for WordPress.  
It allows you to visually organize boards, lists, and cards for tasks, tickets, ideas, etc.  
Extend functionality with official add-ons (e.g., Frontend Interactions, Smart Tags).

---

## Compatibility

- **WordPress:** 5.8+ (tested up to 6.5+)
- **PHP:** 7.2 or higher (compatible with PHP 8.x)


## Features

- Kanban board layout in WordPress admin.
- Cards with title, description, assignee, colored tags, and custom fields.
- Add, edit, and move cards between lists with drag & drop.
- Assign cards to any registered user.
- Colored tag support (with the Alba Card Tags add-on).
- Modal editor for quick card edits in the backend (title, description, assignee, custom fields).
- AJAX-powered saving and updates.
- Secure: permissions and nonces for all sensitive actions.
- Extendable via add-ons (see below).

---

## What’s New (June 2025)

- **Modal backend editor:** Click any card in the backend board to open a modal for editing.
- **Live user selection (Select2):** Assign cards to any user using a live-search dropdown.
- **Custom fields editing:** All visible meta fields (except those starting with `_`) are now editable in the modal.
- **Frontend delete/add:** Add and delete cards from the frontend only if the Frontend Interactions add-on is active.
- **UI/UX cleanup:** No more debug panels or duplicate delete buttons.
- **Bugfixes:** Board CSS restored (true Kanban columns), instant modal updates, correct assignee display after save.

---

## Folder Structure

---

## Installation

1. Upload `alba-board` to your `/wp-content/plugins/` directory.
2. Activate **Alba Board** (and any desired add-ons) from the WordPress admin.
3. Access the backend board via **Tableros > Vista Tablero**.
4. To enable card creation and deletion on the frontend, activate the `alba-board-frontend-interactions` add-on.
5. For Select2 user search, make sure `select2.min.js` and `select2.min.css` are properly enqueued in the admin.

---

## Usage

- **Backend Kanban:** Use drag & drop to reorder or move cards between lists.
- **Modal Editor:** Click any card to open the modal and edit its fields. Save to update instantly.
- **Assignee Selection:** The user dropdown in the modal supports searching via Select2.
- **Frontend:** To add or remove cards from the frontend, activate the relevant add-on.
- **Tags:** Assign colored tags to cards using the Card Tags add-on.

---

## Add-ons

- **Alba Board Frontend Interactions:**  
  Adds card creation and deletion to the frontend UI.

- **Alba Card Tags:**  
  Lets you create and assign colored tags to cards.

More add-ons are planned!

---

## Security

- All AJAX endpoints use nonces and capability checks (`edit_posts`, `edit_others_posts`).
- All field data is sanitized on save.

---

## FAQ

**Q:** Can I extend Alba Board?  
**A:** Yes! Use the provided hooks and includes to build your own add-ons.

**Q:** I see all users in the assignee dropdown—is this configurable?  
**A:** By default, all registered users are listed. You can filter the user query in `includes/ajax-card-details-admin.php` as needed.

**Q:** Where can I report bugs?  
**A:** Please open an issue or PR on the official repository, or contact the plugin author.

---

## License

GPLv2 or later

---

**Built with ❤️ by Alejo and contributors.**

---

# Español

(Sección resumida. Si quieres el texto completo en español, pídelo aquí)

---

¿Necesitas un ejemplo de uso, snippet de código, o guía de hooks?  
Solo pídelo 🚀