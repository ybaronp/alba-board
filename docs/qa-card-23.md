# Card #23: opening card details with either permalink style

This fix is automatic. It adds no setting and does not change your site's permalink structure or access permissions.

## Simple manual check

Use a disposable test or staging site with the PR version of Alba Board installed. Do not change production or the planning site's permalinks for this check. If staging is synchronized with production, prevent those test settings/data from being pushed back.

After installing the QA version, clear any test-site page cache and reload the browser so the updated PHP configuration and JavaScript are loaded together.

1. Sign in as an administrator. Create a board, a list, and a card named **Deliver supplies**. Put **Delivery address: Community Center, Room 23.** in its description. Publish a test page with that board's shortcode.
2. In **Settings → Permalinks**, choose **Plain** and save. Open the card from both the admin board and the published test page while signed in. **Expected:** its description and permitted comments appear, without an error.
3. Choose **Post name** and save. Repeat both card-opening checks. **Expected:** the same details appear.
4. Sign out and open the published test page. Click the card. **Expected:** access to details is denied; the private description is not returned. Sign in with an ordinary Subscriber account and repeat: that account must also remain unable to access these details.
5. Sign back in with the authorized account and confirm opening still works. Restore your test site's original permalink setting after testing.

The public board may still show card titles while signed out; this change does not alter board visibility policy. Seeing a title is different from permission to read its details.

## Checks performed for this change

- Baseline: clicking cards in the unmodified plugin on a real WordPress site with Plain permalinks reproduced HTTP 404 (`rest_no_route`) in both admin and frontend views.
- Fixed version: 16 Chromium/Playwright scenarios covered Plain and Post name permalinks, root and `/subsite` installations, authorized admin/frontend access, anonymous denial (401), and Subscriber denial (403).
- Two more browser scenarios verified that an invalid REST nonce is rejected first, renewed once by the existing session handler, and retried successfully with both permalink styles.
- WordPress 7.1.3, PHP 8.3.36, MariaDB 11.8; test data and users were disposable. No planning/production permalink or plugin settings were changed.
- PHP syntax checks and JavaScript syntax checks passed for the changed source files.

## Repeatable URL regression checks

From the repository root, with Node.js installed:

```sh
node --test tests/card-details-url.test.cjs
```

These 13 checks exercise the actual shared URL builder with both view contexts, plain/pretty/index routes, subdirectories, extra query parameters, HTTPS/port normalization, and rejection of foreign origins. They supplement the browser checks; they do not substitute for WordPress login or permission testing.
