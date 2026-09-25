/**
 * Retell Voice Agent Integration for Restaurant Reservation System
 *
 * Provides browser-based voice calling with Retell AI agents.
 * Call buttons pass contextual data (guest info, reservation details) to the agent.
 * Audio happens through the browser microphone and speakers.
 */

(function() {
    'use strict';

    let retellClient = null;
    let activeCallId = null;
    let callStartTime = null;
    let callTimerInterval = null;

    /**
     * Initialize Retell integration
     */
    function init() {
        if (typeof retellClientJsSdk === 'undefined' || !retellClientJsSdk.RetellWebClient) {
            console.warn('Retell SDK not loaded yet, skipping init');
            return;
        }

        retellClient = new retellClientJsSdk.RetellWebClient();
        setupEventListeners();
        bindClickHandlers();
        createCallOverlay();
    }

    /**
     * Create the call overlay HTML (hidden by default)
     */
    function createCallOverlay() {
        if (document.getElementById('retell-call-overlay')) return;

        const overlay = document.createElement('div');
        overlay.id = 'retell-call-overlay';
        overlay.className = 'retell-overlay';
        overlay.style.display = 'none';
        overlay.innerHTML = `
            <div class="retell-overlay-content" id="retell-overlay-content">
                <div class="retell-overlay-header" id="retell-overlay-header">
                    <div class="retell-pulse-ring" id="retell-pulse-ring">
                        <i class="feather-phone"></i>
                    </div>
                    <h5 class="mt-3 mb-1" id="retell-call-title">AI Voice Agent</h5>
                    <p class="text-muted mb-0" id="retell-call-subtitle"></p>
                </div>
                <div class="retell-overlay-status" id="retell-overlay-status">
                    <span id="retell-status-text">Connecting...</span>
                </div>
                <div class="retell-overlay-timer" id="retell-overlay-timer" style="display:none;">
                    <span id="retell-call-timer" class="font-monospace">00:00</span>
                </div>
                <div class="retell-overlay-context" id="retell-overlay-context" style="display:none;">
                </div>
                <div class="retell-overlay-actions" id="retell-overlay-actions">
                    <button class="btn btn-danger btn-lg rounded-pill px-5" id="retell-end-call-btn" onclick="window.RetellIntegration.endCall()">
                        <i class="feather-phone-off me-2"></i> End Call
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
    }

    /**
     * Set up Retell client event listeners
     */
    function setupEventListeners() {
        retellClient.on('call_started', function() {
            updateOverlayStatus('active', 'Call Connected');
            startTimer();
        });

        retellClient.on('call_ended', function() {
            updateOverlayStatus('ended', 'Call Ended');
            stopTimer();
            setTimeout(hideOverlay, 1500);
        });

        retellClient.on('agent_start_talking', function() {
            updateOverlayStatus('agent_talking', 'Agent Speaking...');
        });

        retellClient.on('agent_stop_talking', function() {
            updateOverlayStatus('active', 'Listening...');
        });

        retellClient.on('error', function(error) {
            console.error('Retell error:', error);
            updateOverlayStatus('error', 'Call error occurred');
            stopTimer();
            setTimeout(hideOverlay, 2000);
        });
    }

    /**
     * Bind click handlers using event delegation
     */
    function bindClickHandlers() {
        document.body.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-retell-call]');
            if (btn) {
                e.preventDefault();
                startCall(btn);
            }
        });
    }

    /**
     * Start a voice call
     * @param {HTMLElement} btn - The button element with data attributes
     */
    async function startCall(btn) {
        if (activeCallId) {
            alert('A call is already in progress');
            return;
        }

        // Gather metadata from data attributes
        var metadata = {};
        var contextLines = [];

        if (btn.dataset.guestName) {
            metadata.guest_name = btn.dataset.guestName;
            contextLines.push('Guest: ' + btn.dataset.guestName);
        }
        if (btn.dataset.guestPhone) {
            metadata.guest_phone = btn.dataset.guestPhone;
            contextLines.push('Phone: ' + btn.dataset.guestPhone);
        }
        if (btn.dataset.guestEmail) {
            metadata.guest_email = btn.dataset.guestEmail;
        }
        if (btn.dataset.partySize) {
            metadata.party_size = btn.dataset.partySize;
            contextLines.push('Party Size: ' + btn.dataset.partySize);
        }
        if (btn.dataset.reservationDate) {
            metadata.reservation_date = btn.dataset.reservationDate;
            contextLines.push('Date: ' + btn.dataset.reservationDate);
        }
        if (btn.dataset.reservationTime) {
            metadata.reservation_time = btn.dataset.reservationTime;
            contextLines.push('Time: ' + btn.dataset.reservationTime);
        }
        if (btn.dataset.confirmationCode) {
            metadata.confirmation_code = btn.dataset.confirmationCode;
            contextLines.push('Confirmation: ' + btn.dataset.confirmationCode);
        }
        if (btn.dataset.reservationStatus) {
            metadata.reservation_status = btn.dataset.reservationStatus;
        }
        if (btn.dataset.specialRequests) {
            metadata.special_requests = btn.dataset.specialRequests;
        }
        if (btn.dataset.restaurantSlug) {
            metadata.restaurant_slug = btn.dataset.restaurantSlug;
        }

        // Show the overlay
        var title = btn.dataset.callTitle || 'AI Voice Agent';
        var subtitle = btn.dataset.callSubtitle || '';
        showOverlay(title, subtitle, contextLines);

        try {
            // Request microphone permission
            await navigator.mediaDevices.getUserMedia({ audio: true });

            // Create web call via our PHP endpoint
            var response = await fetch('/api/retell/create-web-call.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    agent_id: btn.dataset.retellAgentId || '',
                    metadata: metadata
                }),
            });

            if (!response.ok) {
                var errorData = await response.json();
                throw new Error(errorData.error || 'Failed to create call');
            }

            var data = await response.json();

            if (!data.access_token) {
                throw new Error('No access token received');
            }

            activeCallId = data.call_id;

            // Start the call with the access token
            await retellClient.startCall({
                accessToken: data.access_token,
            });

        } catch (error) {
            console.error('Failed to start call:', error);

            if (error.name === 'NotAllowedError') {
                updateOverlayStatus('error', 'Microphone access denied');
            } else {
                updateOverlayStatus('error', error.message || 'Failed to start call');
            }

            stopTimer();
            setTimeout(hideOverlay, 2500);
            activeCallId = null;
        }
    }

    /**
     * End the current call
     */
    function endCall() {
        if (retellClient) {
            retellClient.stopCall();
        }
        activeCallId = null;
        stopTimer();
        updateOverlayStatus('ended', 'Call Ended');
        setTimeout(hideOverlay, 1000);
    }

    /**
     * Show the call overlay
     */
    function showOverlay(title, subtitle, contextLines) {
        var overlay = document.getElementById('retell-call-overlay');
        if (!overlay) {
            createCallOverlay();
            overlay = document.getElementById('retell-call-overlay');
        }

        document.getElementById('retell-call-title').textContent = title;
        document.getElementById('retell-call-subtitle').textContent = subtitle;
        document.getElementById('retell-status-text').textContent = 'Connecting...';
        document.getElementById('retell-call-timer').textContent = '00:00';
        document.getElementById('retell-overlay-timer').style.display = 'none';

        // Show context info if available
        var contextEl = document.getElementById('retell-overlay-context');
        if (contextLines && contextLines.length > 0) {
            contextEl.innerHTML = contextLines.map(function(line) {
                return '<div class="retell-context-line">' + escapeHtml(line) + '</div>';
            }).join('');
            contextEl.style.display = 'block';
        } else {
            contextEl.style.display = 'none';
        }

        // Reset pulse ring state
        var ring = document.getElementById('retell-pulse-ring');
        ring.className = 'retell-pulse-ring connecting';

        overlay.style.display = 'flex';
    }

    /**
     * Hide the call overlay
     */
    function hideOverlay() {
        var overlay = document.getElementById('retell-call-overlay');
        if (overlay) overlay.style.display = 'none';
        activeCallId = null;
    }

    /**
     * Update overlay status display
     */
    function updateOverlayStatus(state, message) {
        var statusText = document.getElementById('retell-status-text');
        var ring = document.getElementById('retell-pulse-ring');
        if (!statusText || !ring) return;

        statusText.textContent = message;
        ring.className = 'retell-pulse-ring ' + state;

        if (state === 'active' || state === 'agent_talking') {
            document.getElementById('retell-overlay-timer').style.display = 'block';
        }
    }

    /**
     * Start call timer
     */
    function startTimer() {
        callStartTime = Date.now();
        callTimerInterval = setInterval(function() {
            var elapsed = Math.floor((Date.now() - callStartTime) / 1000);
            var mins = String(Math.floor(elapsed / 60)).padStart(2, '0');
            var secs = String(elapsed % 60).padStart(2, '0');
            var timerEl = document.getElementById('retell-call-timer');
            if (timerEl) timerEl.textContent = mins + ':' + secs;
        }, 1000);
    }

    /**
     * Stop call timer
     */
    function stopTimer() {
        if (callTimerInterval) {
            clearInterval(callTimerInterval);
            callTimerInterval = null;
        }
    }

    /**
     * Escape HTML entities
     */
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose endCall globally for the overlay button
    window.RetellIntegration = {
        endCall: endCall
    };

})();
