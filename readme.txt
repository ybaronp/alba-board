=== Alba Board ===
Contributors: alejo30  
Tags: kanban, board, project management, todo, task
Requires at least: 5.8  
Tested up to: 6.9.4
Requires PHP: 7.2
Stable tag: 2.1.1
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  

Custom Kanban for WordPress. Organize tasks, projects, and teams with a modern board UI, Premium Themes, and Smart Tags. 🌍

== Description ==

👋 **A quick note from the developer:** *Alba Board is an actively growing plugin. My main goal is to build the most intuitive, visually stunning, and genuinely useful project management tool for WordPress. Because we are new, **your feedback directly shapes our roadmap.** Missing a feature? Is something not working as expected? Please let me know! It only takes 60 seconds: [💬 Share your feedback here](https://albaboard.com/).*

---

🎬 **Watch it in action:** https://www.youtube.com/watch?v=nCBS9oGEhEo

**Alba Board** brings an intuitive, modern Kanban board directly to your WordPress site. Whether you need it for project management, task tracking, client workflows, editorial calendars, or personal productivity, Alba Board provides a seamless, app-like experience right inside your WP admin dashboard and on your public pages.

💡 **Why choose Alba Board?**

* 🎨 **Visual Theme Engine:** Enjoy a polished, modern aesthetic with our dynamic theme selector. Choose between Clean Canvas, Deep Space, or unlock premium Glassmorphism and Neumorphism styles.
* 🖱️ **Visual & Fluid:** Manage cards and rearrange entire lists using butter-smooth drag-and-drop mechanics (powered by Sortable.js and AJAX).
* 📜 **Independent List Scrolling:** Say goodbye to infinitely long pages. Columns intelligently adapt to your screen height with elegant, independent scrollbars.
* 📎 **Rich Task Management:** Add file attachments, custom fields, colored tags, assignees, and detailed descriptions to any card.
* 🌍 **Multi-Language Ready:** Completely translation-ready (i18n). Alba Board adapts to your native language so your entire team feels at home.
* 📊 **Stay on Top of Things:** Includes a native WordPress Dashboard Widget so users can instantly see their assigned tasks upon logging in.
* 🔐 **Your Data, Your Rules:** No vendor lock-in. Administrators can export entire boards to CSV or JSON formats with a single click.

🔗 **Full documentation, guides & more:** [https://albaboard.com/](https://albaboard.com/)

== Features ==

* 📋 Kanban-style board view seamlessly integrated into WP admin and Frontend
* 🎨 Dynamic Theme Engine (Soft UI, Dark Mode, Glassmorphism)
* 🚀 Drag & drop cards and entire lists with zero lag
* 📜 Independent vertical scrolling for long lists
* 📂 File attachments natively inside cards
* 📝 Rich card editor: title, description, assignee, tags, and custom fields
* 💬 Comments UI (admin + frontend) for effortless team collaboration
* 🌍 100% Translation-ready & Multilingual support
* 📌 Dashboard widget: "My Alba Board Tasks"
* 📥 Data Export (CSV/JSON) for easy backups
* 👥 Select users as assignees with live search & avatars
* 🧩 Extendable via Add-ons and a robust Hooks/Actions API
* 📱 Desktop & mobile friendly interface
* 🛡️ Built securely: strictly uses WordPress user permissions & nonces

== Add-ons & Extensions ==

**⚡ Supercharge your workflow with official add-ons:**

* 💎 **Alba Board Pro: Customization & Smart Tags:** Unlocks 7 Premium Themes (Cyberpunk Neon, Stellar Earth, Vaporwave, etc.) and adds dynamic color-coded Smart Tags with frictionless inline creation.
* 🌐 **Alba Board Frontend Interactions:** Enable seamless card creation, commenting, and secure deletion directly from your site’s public side (frontend).

> *🚀 More add-ons are in the works! Have a specific request? [Let us know!](https://albaboard.com/)*

== Installation ==

1. 📂 Upload the `alba-board` folder to `/wp-content/plugins/`.
2. 🔌 Activate **Alba Board** from the Plugins menu in WordPress.
3. 🗂️ Access your board in the admin panel under: **Alba Board**.
4. ⚙️ Customize your colors and limits under **Alba Board > Settings**.
5. *Note: For the assignee dropdown to work perfectly, ensure `select2.min.js` and `select2.min.css` are allowed to load.*

== Support, Docs & Feedback ==

We are building this tool for you, and we want to hear from you!

* 🗣️ **Share Feedback & Feature Requests:** [https://albaboard.com/](https://albaboard.com/)
* 📖 **Docs, guides & demos:** [https://albaboard.com/docs/](https://albaboard.com/docs/)
* ⭐ **Rate us & leave a review:** As a new plugin, your reviews make a massive difference in helping others find us!

== Frequently Asked Questions ==

= 🙋‍♂️ How can I suggest a new feature or report a bug? =
We love suggestions! Since Alba Board is actively being developed, your input is highly valued. Please drop us a quick message via our website: https://albaboard.com/

= 🌍 Is Alba Board available in my language? =
Yes! The plugin is completely translation-ready. If it hasn't been translated into your language yet, you can easily translate it using plugins like Loco Translate, or contribute to the community translations.

= 🌐 How do I enable frontend card creation? =  
Activate the “Alba Board Frontend Interactions” add-on from the Add-ons menu.

= 🎨 How do I unlock Premium Themes and colored tags? =  
Enable the “Alba Board Pro: Customization & Smart Tags” add-on to unlock 7 spectacular themes and intuitive inline tag management.

= 💻 Can developers extend Alba Board? =  
Yes! We've implemented a robust Hooks/Actions API (`do_action`) allowing developers to extend functionalities via custom code or add-ons without modifying core files.

= 🔒 Is frontend card management secure? =  
Absolutely. All AJAX actions use WordPress nonces and capability checks to ensure your data stays safe and cache-proof.

== Screenshots ==

1. Admin board view with beautiful Neumorphism UI and custom themes.
2. Independent list scrolling with customized scrollbars.
3. Card details showing Smart Tags, file attachments, and comments.
4. Kanban view seamlessly integrated into the front end using shortcodes.

== Changelog ==

= 2.1.1 =
* Security: Patched an IDOR (Insecure Direct Object Reference) vulnerability in the REST API and AJAX endpoints that allowed unauthorized users to view private card details.
= 2.1.0 =
* Feature: Ultra-Slim List Collapsing. Users can now collapse lists to a slim view to maximize board space.
* Feature: Dates are now available, whether you want to see them from the card on the list or when opening your card.
* UX/UI: User-Specific Persistence. Collapsed states are saved per-user using LocalStorage, ensuring a personalized workspace that doesn't affect other team members.
* UX/UI: Intuitive Geometric Icons. Pure CSS-driven symmetric arrows for collapsing (><) and expanding (<>) that adapt perfectly to vertical titles.
* Architecture: Deep REST API Integration. Finalized the migration for card data fetching to bypass legacy admin-ajax.php, significantly reducing server overhead. 
* Performance: Optimized Front-end & Back-end synchronization. List states are now perfectly mirrored between the public board and the admin dashboard.

= 2.0.0 =
* Feature: New Visual Theme Engine! Alba Board has been redesigned from the ground up with a dynamic theme selector featuring beautiful UI styles.
* Feature: Independent List Scrolling. Columns now adapt to your screen height with elegant, independent vertical scrollbars. The board no longer stretches infinitely!
* Feature: 100% Integrated Front-End. The `[alba_board]` shortcode now perfectly inherits your selected Premium Themes, shadows, and glass modals for a seamless user experience.
* Feature: Launched "Alba Board Pro: Customization & Smart Tags" Add-on! Unlocks 7 exclusive premium themes (Cyberpunk Neon, Stellar Earth, Vaporwave, etc.) and dynamic Smart Tags.
* UX/UI: Implemented a smooth, cache-proof frontend card deletion flow. Cards now vanish gracefully without reloading the page or showing duplicate alerts.
* UX/UI: Neumorphic comments section with sculpted design and modern buttons for cleaner readability.
* Fix: Prevented cards from squishing or compressing when a list gets too full.
* Enhancement: Added a deactivation feedback system to help us listen to your needs, gather insights, and continuously improve the product.

= 1.4.0 =
* Feature: List Reordering! You can now drag and drop entire lists by their header to reorganize your board seamlessly.
* Feature: Delete Lists. Added a quick-action 'X' button (visible on hover) to easily delete a list and its cards directly from the board.
* Feature: Inline Tag Creation. Create new tags on the fly simply by typing them into the card modal's tag selector and hitting Enter.
* UX/UI: Unified Admin Menu. All Alba Board options and databases are now elegantly nested under a single master menu for a cleaner WordPress dashboard.
* UX/UI: Contextual List Creation. The "Add List" action is now a seamless phantom column at the right edge of your board.
* Fix: Prevented the "Add Card" footer button from shifting out of place while dragging cards.

= 1.3.2 =
* Architecture: Implemented a robust Hooks/Actions API (`do_action`) across the frontend and backend.
* Enhancement: Added JavaScript custom events (`alba_modal_loaded`) to allow Add-ons to reliably initialize scripts.
* Accessibility (Frontend): Synchronized power-user keyboard shortcuts to the frontend view (Esc to close, Ctrl+Enter to submit).

= 1.3.0 =
* Feature: File Attachments! You can now upload and manage files directly inside cards.
* Feature: Dashboard Widget. Added a native WordPress dashboard widget "My Alba Board Tasks".
* Feature: Data Export. Administrators can now export entire boards to CSV or JSON formats with a single click.

= 1.2.0 =
* Feature: User Avatars! Cards now display the assigned user's profile picture on both the frontend and backend boards.
* Feature: 1-Click Onboarding. Added a "Create a Sample Board" empty state for new users to instantly generate a fully functional demo board.

= 1.0.0 =
* First stable release — backend Kanban, modal editing, AJAX, comments, add-ons.