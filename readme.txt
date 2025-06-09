=== Alba Board ===
Contributors: alejo  
Tags: kanban, board, project management, todo, task, cards, workflow  
Requires at least: 5.8  
Tested up to: 6.8.1  
Requires PHP: 7.2  
Stable tag: 1.0  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  

Custom Kanban system for WordPress with boards, lists, cards, dynamic user assignment, tags and extensibility via add-ons. Visit our site for more information and try our demo [Live Demo](https://albaboard.com/)

== Description ==

**Alba Board** brings a flexible Kanban board to your WordPress admin and (optionally) to the frontend.  
Desktop and Mobile friendly.

Create, organize, and manage boards, lists, and cards, assign tasks, add custom fields and colored tags, and extend with official add-ons.

*Unified comment feedback UI for frontend and backend. Improved AJAX messaging, accessibility, and performance.*

== Features ==

* Kanban-style board view in WordPress admin
* Cards with title, description, assignee, colored tags, custom fields
* Drag & drop cards between lists (Sortable.js)
* Fast modal editing (backend): edit title, description, assignee, and meta
* Unified feedback for adding comments in both admin and frontend (green success/red error below the comment box)
* AJAX-powered updates for all board actions
* All registered users can be assigned as card assignees (Select2 live search)
* Colored tag support (with the Card Tags add-on)
* Secure: all actions require proper permissions and nonces
* Extendable via official add-ons

== Permissions and Capabilities ==

Upon activation, **Alba Board** automatically assigns all necessary capabilities for boards, lists, and cards to the Administrator and Editor roles.  
This ensures these users can always see and manage Alba Board content, even on a fresh WordPress install.

You may also use plugins like **User Role Editor** to manage these capabilities visually.

== Add-ons ==

Alba Board’s power can be expanded with official add-ons. The following are included and maintained by the author:

* **Alba Board Frontend Interactions**  
  - Enables card creation and deletion from the frontend (public site).
  - Can be activated or deactivated independently.
  - When deactivated, the frontend buttons for adding/removing cards are hidden.

* **Alba Card Tags**  
  - Lets you create colored tags and assign them to cards for easier categorization and filtering.
  - You can manage tag colors and assign multiple tags per card.

> More add-ons are planned! Developers can also create their own add-ons using WordPress hooks and Alba Board’s modular structure.

== Installation ==

1. Upload the `alba-board` directory to `/wp-content/plugins/`.
2. Activate **Alba Board** from the Plugins menu.
3. Optionally activate any official add-ons you need (`Alba Board Frontend Interactions`, `Alba Card Tags`).
4. Access your board in the admin via "Boards > Board View" (`Tableros > Vista Tablero`).
5. To use Select2 for assignee dropdown, ensure `select2.min.js` and `select2.min.css` are present and enqueued in admin.

== Frequently Asked Questions ==

= Can I extend Alba Board? =  
Yes! Use hooks or create add-ons for new functionality.

= How do I enable frontend card creation and deletion? =  
Activate the "Alba Board Frontend Interactions" add-on from the Add-ons folder.

= How do I use colored tags? =  
Activate the "Alba Card Tags" add-on. You will then be able to create, assign, and manage colored tags for each card.

= Is frontend card creation secure? =  
Yes. AJAX actions are protected by WordPress nonces and capability checks.

= Can I customize the board design? =  
You can override CSS in your theme or create add-ons to extend functionality.

== Changelog ==

= 1.0 =
* Initial release.
* Backend Kanban board.
* Modal editing for cards (title, content, assignee, custom fields).
* Unified comment feedback for frontend and backend.
* All users available for assignment (Select2 search).
* Frontend add/delete with add-on.
* Colored tags with add-on.
* Bugfixes, improved AJAX messaging, and refined UI.

== Upgrade Notice ==

= 1.0 =
First stable release.

== Screenshots ==

1. Kanban view in the front end
2. View from the Back End
3. Back End Card view with fields and Comments
4. Front End view with Comments