/**
 * Acceptance Certificate Designer Plugin - Workflow UI Integration
 * Specifically optimized for OJS 3.5 Tailwind/SideModal Workflow Header
 */
(function() {
    'use strict';

    console.log('[AcceptanceLetter] workflow.js running...');

    // Cache eligibility per submissionId so we don't spam network requests
    var eligibilityCache = {};

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

        // 3. Match from URL (e.g. /workflow/access/62078 or /workflow/editorial/62078)
        var urlMatch = window.location.href.match(/\/workflow(?:\/[^\/\?#]+)*\/(\d+)/i);
        if (urlMatch && urlMatch[1]) {
            return urlMatch[1];
        }

        // 4. Query string
        var params = new URLSearchParams(window.location.search);
        return params.get('submissionId') || params.get('workflowSubmissionId');
    }

    function getJournalPath() {
        var segments = window.location.pathname.split('/');
        var indexIdx = segments.indexOf('index.php');
        if (indexIdx !== -1 && segments[indexIdx + 1]) {
            return segments[indexIdx + 1];
        }
        return 'test';
    }

    function removeButtons() {
        var hBtn = document.getElementById('pkp-acceptance-letter-header-btn');
        if (hBtn) hBtn.remove();
        var hEmailBtn = document.getElementById('pkp-acceptance-letter-header-email-btn');
        if (hEmailBtn) hEmailBtn.remove();
        var floatContainer = document.getElementById('pkp-acceptance-letter-floating-container');
        if (floatContainer) floatContainer.remove();
    }

    /**
     * Check if the submission is in Copyediting (stage 4), Production (stage 5), or Published.
     * Uses DOM cues first if available, and queries the backend checkEligibility endpoint as authoritative source.
     */
    function checkEligibility(submissionId, journalPath, callback) {
        if (eligibilityCache[submissionId] !== undefined) {
            callback(eligibilityCache[submissionId]);
            return;
        }

        // Check if cached as pending request
        if (checkEligibility.pending && checkEligibility.pending[submissionId]) {
            return;
        }
        if (!checkEligibility.pending) {
            checkEligibility.pending = {};
        }
        checkEligibility.pending[submissionId] = true;

        var url = '/index.php/' + journalPath + '/acceptanceWorkflow/checkEligibility?submissionId=' + submissionId;
        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            delete checkEligibility.pending[submissionId];
            var isEligible = false;
            if (data && data.status === true && data.content) {
                isEligible = Boolean(data.content.eligible);
            }
            eligibilityCache[submissionId] = isEligible;
            callback(isEligible);
        })
        .catch(function(err) {
            delete checkEligibility.pending[submissionId];
            console.error('[AcceptanceLetter] Error checking eligibility:', err);
            // Default to false on error to satisfy requirements strictly
            callback(false);
        });
    }

    function injectButton() {
        var submissionId = getSubmissionId();
        if (!submissionId) {
            removeButtons();
            return;
        }

        var journalPath = getJournalPath();

        checkEligibility(submissionId, journalPath, function(isEligible) {
            // Re-verify submissionId has not changed while network request was in flight
            if (getSubmissionId() !== submissionId) {
                return;
            }

            if (!isEligible) {
                removeButtons();
                return;
            }

            var downloadUrl = '/index.php/' + journalPath + '/acceptanceWorkflow/downloadPdf?submissionId=' + submissionId;
            var sendEmailUrl = '/index.php/' + journalPath + '/acceptanceWorkflow/sendEmail?submissionId=' + submissionId;

            function triggerSendEmail(btnEl) {
                if (!confirm('Send the official acceptance certificate and certificate directly to the author via email?')) {
                    return;
                }
                var origHtml = btnEl.innerHTML;
                btnEl.disabled = true;
                btnEl.innerHTML = '<span>⏳</span> Sending...';

                fetch(sendEmailUrl, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    btnEl.disabled = false;
                    btnEl.innerHTML = origHtml;
                    if (data && data.status === true) {
                        var msg = (data.content && data.content.message) ? data.content.message : 'Acceptance certificate sent successfully!';
                        if (window.pkp && pkp.eventBus) {
                            pkp.eventBus.$emit('notify', msg, 'success');
                        } else {
                            alert(msg);
                        }
                    } else {
                        var err = (data && data.content) ? (typeof data.content === 'string' ? data.content : data.content.message) : 'Failed to send email.';
                        alert(err || 'Failed to send email.');
                    }
                })
                .catch(function(err) {
                    btnEl.disabled = false;
                    btnEl.innerHTML = origHtml;
                    alert('Network error while sending email: ' + err.message);
                });
            }

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
                    btn.innerHTML = '<span>📜</span> Acceptance Certificate';
                    btn.title = 'Generate & Download Official Acceptance Certificate (PDF)';

                    headerBar.appendChild(btn);
                    console.log('[AcceptanceLetter] Attached button to top header for submission:', submissionId);
                } else {
                    // Update URL if submission changed
                    if (existingBtn.href !== downloadUrl) {
                        existingBtn.href = downloadUrl;
                    }
                }

                var existingEmailBtn = document.getElementById('pkp-acceptance-letter-header-email-btn');
                if (!existingEmailBtn) {
                    var emailBtn = document.createElement('button');
                    emailBtn.id = 'pkp-acceptance-letter-header-email-btn';
                    emailBtn.type = 'button';
                    emailBtn.className = 'pkpButton inline-flex relative items-center gap-x-1 text-lg-semibold text-primary border-light hover:text-hover disabled:text-disabled bg-secondary py-[0.4375rem] px-3 border rounded';
                    emailBtn.style.cssText = 'text-decoration: none; cursor: pointer;';
                    emailBtn.innerHTML = '<span>✉️</span> Send Acceptance Certificate to Author';
                    emailBtn.title = 'Send official acceptance certificate and certificate directly to author via email';
                    emailBtn.onclick = function() {
                        triggerSendEmail(emailBtn);
                    };
                    headerBar.appendChild(emailBtn);
                }
            }

            // Target 2: Floating button container at bottom right (always visible)
            var existingContainer = document.getElementById('pkp-acceptance-letter-floating-container');
            if (!existingContainer) {
                var container = document.createElement('div');
                container.id = 'pkp-acceptance-letter-floating-container';
                container.style.cssText = 'position: fixed; bottom: 25px; right: 25px; z-index: 999999; display: flex; gap: 8px; align-items: center;';

                // var floatBtn = document.createElement('a');
                // floatBtn.id = 'pkp-acceptance-letter-floating-btn';
                // floatBtn.href = downloadUrl;
                // floatBtn.target = '_blank';
                // floatBtn.style.cssText = 'display: flex; align-items: center; gap: 6px; padding: 9px 16px; background: #006798; color: #ffffff; border-radius: 25px; font-weight: 600; font-size: 13px; text-decoration: none; box-shadow: 0 4px 15px rgba(0,0,0,0.3); border: 2px solid #ffffff; cursor: pointer;';
                // floatBtn.innerHTML = '<span>📜</span> Acceptance Certificate';
                // floatBtn.title = 'Download Acceptance Certificate PDF';
                // container.appendChild(floatBtn);

                // var floatEmailBtn = document.createElement('button');
                // floatEmailBtn.id = 'pkp-acceptance-letter-floating-email-btn';
                // floatEmailBtn.type = 'button';
                // floatEmailBtn.style.cssText = 'display: flex; align-items: center; gap: 6px; padding: 9px 16px; background: #0284c7; color: #ffffff; border-radius: 25px; font-weight: 600; font-size: 13px; text-decoration: none; box-shadow: 0 4px 15px rgba(0,0,0,0.3); border: 2px solid #ffffff; cursor: pointer;';
                // floatEmailBtn.innerHTML = '<span>✉️</span> Send Acceptance Certificate to Author';
                // floatEmailBtn.title = 'Send Acceptance Certificate directly to author via email';
                // floatEmailBtn.onclick = function() {
                //     triggerSendEmail(floatEmailBtn);
                // };
                container.appendChild(floatEmailBtn);

                document.body.appendChild(container);
            } else {
                var existingFloat = document.getElementById('pkp-acceptance-letter-floating-btn');
                if (existingFloat && existingFloat.href !== downloadUrl) {
                    existingFloat.href = downloadUrl;
                }
            }
        });
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
