=== Alba Board - Custom Kanban Board and Task Management ===
Contributors: alejo30  
Tags: kanban, kanban-board, task-management, project-management, workflow
Requires at least: 5.8  
Tested up to: 7.1.2
Requires PHP: 7.4
Stable tag: 2.2.0
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

* 💎 **Alba Board Pro: Customization & Smart Tags:** Unlocks 12 Premium Themes (Cyberpunk Neon, Stellar Earth, Vaporwave, etc.) and adds dynamic color-coded Smart Tags with frictionless inline creation.
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

= 📦 How do I restore an archived card? =
In **Alba Board > Boards**, open **More > Archived Cards** and click **Restore** beside the card. It returns to the list it came from and its previous status.

If the card says **Restore the original list first**, that list is in the WordPress Trash. Open **Alba Board > Lists**, choose the **Trash** view, and restore the original list. Then return to **More > Archived Cards** and restore the card. The archive view also has an **Open Lists Trash** link for this case. If the original list was permanently deleted, restore it from a site backup before restoring the card.

Deleting a list from a board moves the list to Trash and archives its cards. The cards remain recoverable from **Archived Cards** after the original list is restored.

= 👥 How do guest assignees work? =
In a card, enter one or more guest email addresses in **Guest Assignee**, separated by commas. When assignment notifications are enabled, Alba Board sends an email to each newly added valid address. To include a public card link, create a published page containing that board’s `[alba_board id="123"]` shortcode. Without a public board page, guest links fall back to the site homepage. Guests cannot use the WordPress admin. Click the **Guest Assignee** field to see missing-page guidance; you can click and copy its shortcode without the message disappearing. An assignment email does not grant permission to view card details or edit cards; access still requires the appropriate WordPress permissions.

= 🗑️ How do I delete or restore a card? =
In the WordPress admin board, open the card and click the trash icon beside the archive icon. Confirm to move the card to WordPress Trash. To recover it, open **Alba Board > Cards > Trash** and click **Restore**. This control is available only to users with permission to delete that card. If WordPress Trash is disabled, the control will not permanently delete the card.

= 🔗 How do I share a direct link to a card? =
Open the card on an admin or public board and copy the page URL, which includes `?alba_card=ID`. Opening that link selects the card on the corresponding board. For guest assignees, use the public board page URL.

= ↔️ How do I use a board across the full page width? =
Use the shortcode attribute `[alba_board id="123" fullwidth="1"]` on the public page.

= 🎨 How do I unlock Premium Themes and colored tags? =  
Enable the “Alba Board Pro: Customization & Smart Tags” add-on to unlock 12 premium themes and intuitive inline tag management.

= 💻 Can developers extend Alba Board? =  
Yes! We've implemented a robust Hooks/Actions API (`do_action`), allowing developers to extend functionalities via custom code or add-ons without modifying core files.

= 🔒 Is frontend card management secure? =  
Frontend card creation, comments, and deletion require a logged-in user with the appropriate permissions and a valid WordPress security token. Publishing a board or assigning a guest email address does not grant permission to access protected card details or change cards.

== Screenshots ==

1. Admin board view with beautiful Neumorphism UI and custom themes.
2. Independent list scrolling with customized scrollbars.
3. Card details showing Smart Tags, file attachments, and comments.
4. Kanban view seamlessly integrated into the front end using shortcodes.

== Changelog ==

= 2.2.0 =
* New: Delete cards from the WordPress admin board using a compact trash icon. Confirmation moves the card to WordPress Trash; restore it under **Alba Board > Cards > Trash**. Permission checks protect each card, and deletion is blocked when WordPress Trash is disabled.
* New: Archive individual cards without archiving their list. Restore them under **Alba Board > Boards > More > Archived Cards**. If their original list is in Trash, restore that list under **Alba Board > Lists > Trash** first.
* New: Assign guests by email, including multiple comma-separated addresses. When assignment notifications are enabled, newly added valid addresses receive emails. Publish a page containing the board shortcode to include a public card link. Guest assignment does not grant permission to view protected card details or edit cards.
* New: Share direct card links by copying the URL after opening a card. The URL includes `?alba_card=ID`.
* New: Copy a board's shortcode from the admin board header, or use `[alba_board id="123" fullwidth="1"]` to display a public board across the page width.
* Improved: Clearer card layout with due date and tags aligned on the same row on desktop when those add-on fields are available. Fields stack on smaller screens, and the card dialog scrolls vertically without a horizontal scrollbar.
* Improved: Compact archive, delete, and upload icons with accessible labels and tooltips. The upload icon sits directly beside **Attachments**. Existing theme colors are preserved.
* Improved: Show missing-public-page guidance only when the **Guest Assignee** field is selected. The message uses theme colors and stays visible while its shortcode is clicked or copied.
* Improved: A compact, responsive top bar for board selection, search, filters, and board actions.
* Improved: Connect five additional premium themes through the Card Tags add-on: Celestial Glass, Neo-Brutalism, High Contrast, Solarized Hacker, and Physical Corkboard. Premium styles remain in the add-on.
* Fixed: Add the moving aurora background to Celestial Glass on backend and frontend boards. Users who prefer reduced motion see a static background.
* Fixed: Theme settings now enable only themes registered by the installed add-on. Newer premium themes remain listed with an update-required message until a supporting add-on is installed, preventing selections that would reset to the default theme.
* Fixed: Restore readable light text on the dark frontend comment button in Zen Monochrome, using the theme's existing colors.
* Fixed: Rapid clicks and repeated Enter or Command/Control + Enter shortcuts no longer create duplicate cards. Frontend creation buttons no longer accidentally submit surrounding page forms.
* Fixed: Cards created on the frontend can be opened immediately without refreshing the page.
* Fixed: Repeated clicks on Save no longer submit duplicate comments.
* Fixed: Hide the archive control on frontend card dialogs; archiving remains available in the WordPress admin.
* Fixed: Improved attachment uploads and error handling so failed uploads are reported instead of failing silently.
* Fixed: Card details refresh after comments, tags, or other card information changes, including changes made by add-ons.
* Security: Require valid security tokens and the appropriate permissions before frontend card creation, comments, or deletion. Enforce configured card limits on the server.
* Security: Check permissions before creating boards, lists, or cards in the admin. Correct missing permissions for administrators and editors working with published or private items.
* Security: Keep card details protected by permission checks in both frontend and admin requests. A published board does not bypass these checks.
* Performance: Load core frontend board scripts and styles only on pages containing a board shortcode. Other integrations can opt in using the `alba_board_should_enqueue_frontend_assets` filter.
* Performance: Fetch board cards together and avoid unnecessary database updates when card or list ordering has not changed.
* Performance: Reduce repeated work while searching and filtering admin boards, and reuse valid cached card details. These changes reduce unnecessary processing; overall speed gains depend on the board size and hosting environment.

= 2.1.5 =
* New: Added more Premium themes and updated some comments within the code.

= 2.1.4 =
* Security: Improved authorization checks to prevent unauthorized data access.

= 2.1.3 =
* Security: Fixed an authorization vulnerability that could allow unauthorized users to view private card details.
* Security: Improved security token (nonce) validation and generation strictness across the board.
* Enhancement: Overhauled CSV and JSON export engines for backend boards. Exports now include comprehensive card data: Assignee, Due Date, Tags, Attachment URLs, and full Conversation/Activity logs.

= 2.1.2 =
* Security: Corrected the IDOR patch in REST API and AJAX endpoints. Migrated away from the flawed 'read_card' meta capability mapping to a strict 'edit_cards' fallback, enforcing rigorous role-based access for non-public boards.

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
