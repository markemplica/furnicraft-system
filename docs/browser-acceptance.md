# Browser and user acceptance checks

## Automated checks

Run `php artisan test` for Laravel feature coverage and `npm run test:browser` for the Playwright smoke suite. The browser suite starts a local Laravel server and checks the public home and login pages at desktop Chromium and iPhone-sized Chromium viewports, page errors, horizontal overflow, image alternatives, form labels, button names, keyboard skip navigation, and `/up`.

The Laravel feature suite covers customer workflows, queued room analysis, Gemini fallback behavior, private room-image storage, recommendation display, and reservation approval notification language. It does not contact Gemini or prove actual email delivery.

## Current browser observation

On October 3, 2026, the public home page and login page were opened in the Codex in-app Chromium browser at a 390px viewport. The home page had no horizontal overflow; it had one main landmark and one level-one heading, and all images and buttons had accessible names. The login page had no overflow and its visible email/password fields were labeled. No browser console errors were observed on the login page. The same pages should be rechecked using the automated command after installing the Node dependencies.

## Release acceptance still required

- Test the same customer and administrator flows in current Chrome, Edge, and Firefox on supported desktop and mobile devices.
- Ask shop staff and representative customers to complete the workflows and record task success, confusing labels, and device/browser details.
- Check keyboard-only navigation, screen-reader announcements, zoom/reflow, and color contrast with dedicated accessibility tools.
- Simulate slow and interrupted network conditions; verify uploads, queue polling, retries, and recovery after reconnecting.
- Exercise Gemini outage/rate-limit behavior and queue recovery in a staging environment; use HTTP fakes for repeatable application tests, not the production Gemini endpoint.
- Run a concurrent-reservation load check against staging MySQL and verify database constraints and resulting statuses.
- Measure page and request performance under representative load, then document the hardware, network, sample size, and thresholds.

Do not treat a passing smoke suite or the in-app browser check as full cross-browser, accessibility, performance, or staff acceptance evidence.
