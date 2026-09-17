/**
 * Acceptance Letter Designer Plugin - Workflow UI Integration
 * Specifically optimized for OJS 3.5 Tailwind/SideModal Workflow Header
 */
(function() {
    'use strict';

    console.log('[AcceptanceLetter] workflow.js running...');

    function getSubmissionId() {
        // 1. Direct match from OJS 3.5 sidemodal header: <div class="text-xl-medium">62078 ...</div>
        var sideModal = document.querySelector('[data-cy="sidemodal-header"]');
        if (sideModal) {
            var numEl = sideModal.querySelector('.text-xl-medium') || sideModal.querySelector('div');
            if (numEl) {
                var m = numEl.textContent.match(/\b(\d{3,7})\b/);
                if (m && m[1]) {
                    return m[1];
                }
            }
        }

        // 2. Check any .text-xl-medium on the page
        var allNumEls = document.querySelectorAll('.text-xl-medium');
        for (var i = 0; i < allNumEls.length; i++) {
            var m2 = allNumEls[i].textContent.match(/\b(\d{3,7})\b/);
            if (m2 && m2[1]) {
                return m2[1];
            }
        }

        // 3. Match from URL (e.g. /workflow/access/62078)
        var urlMatch = window.location.href.match(/\/workflow(?:\/[^\/\?#]+)*\/(\d+)/i);
        if (urlMatch && urlMatch[1]) {
            return urlMatch[1];
        }

        // 4. Query string
        var params = new URLSearchParams(window.location.search);
        return params.get('submissionId');
    }

    function getJournalPath() {
        var segments = window.location.pathname.split('/');
        var indexIdx = segments.indexOf('index.php');
        if (indexIdx !== -1 && segments[indexIdx + 1]) {
            return segments[indexIdx + 1];
        }
        return 'test';
    }

    function injectButton() {
        var submissionId = getSubmissionId();
        if (!submissionId) {
            return;
        }

        var journalPath = getJournalPath();
        var downloadUrl = '/index.php/' + journalPath + '/acceptanceWorkflow/downloadPdf?submissionId=' + submissionId;

        // Target 1: The OJS 3.5 flex button bar containing Payments, Preview, Activity Log, Library
        var headerBar = document.querySelector('[data-cy="sidemodal-header"] .flex.gap-x-4')
            || document.querySelector('[data-cy="sidemodal-header"] .flex.flex-none.items-center > div')
            || document.querySelector('.pkpWorkflow__submissionPayments')?.parentElement;

        if (headerBar) {
            var existingBtn = document.getElementById('pkp-acceptance-letter-header-btn');
            if (!existingBtn) {
                var btn = document.createElement('a');
                btn.id = 'pkp-acceptance-letter-header-btn';
                btn.href = downloadUrl;
                btn.target = '_blank';
                // Exact matching OJS 3.5 Tailwind button classes
                btn.className = 'pkpButton inline-flex relative items-center gap-x-1 text-lg-semibold text-primary border-light hover:text-hover disabled:text-disabled bg-secondary py-[0.4375rem] px-3 border rounded';
                btn.style.cssText = 'text-decoration: none; cursor: pointer;';
                btn.innerHTML = '<span>📜</span> Acceptance Letter';
                btn.title = 'Generate & Download Official Acceptance Letter (PDF)';

                headerBar.appendChild(btn);
                console.log('[AcceptanceLetter] Attached button to top header for submission:', submissionId);
            } else {
                // Update URL if submission changed
                if (existingBtn.href !== downloadUrl) {
                    existingBtn.href = downloadUrl;
                }
            }
        }

        // Target 2: Floating button at bottom right (always visible)
        var existingFloat = document.getElementById('pkp-acceptance-letter-floating-btn');
        if (!existingFloat) {
            var floatBtn = document.createElement('a');
            floatBtn.id = 'pkp-acceptance-letter-floating-btn';
            floatBtn.href = downloadUrl;
            floatBtn.target = '_blank';
            floatBtn.style.cssText = 'position: fixed; bottom: 25px; right: 25px; z-index: 999999; display: flex; align-items: center; gap: 8px; padding: 10px 18px; background: #006798; color: #ffffff; border-radius: 25px; font-weight: 600; font-size: 14px; text-decoration: none; box-shadow: 0 4px 15px rgba(0,0,0,0.3); border: 2px solid #ffffff; cursor: pointer;';
            floatBtn.innerHTML = '<span>📜</span> Acceptance Letter';
            floatBtn.title = 'Download Acceptance Certificate PDF';
            document.body.appendChild(floatBtn);
        } else {
            if (existingFloat.href !== downloadUrl) {
                existingFloat.href = downloadUrl;
            }
        }
    }

    // Run periodically to catch SideModal opens
    setInterval(injectButton, 400);

    if (window.MutationObserver) {
        var observer = new MutationObserver(function() {
            injectButton();
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }
})();
