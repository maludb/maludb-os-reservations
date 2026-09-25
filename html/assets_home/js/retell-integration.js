/**
 * Retell Voice Agent Integration
 *
 * Handles voice calls with Retell AI agents via dashboard cards
 */

(function() {
    'use strict';

    // Retell client instance
    let retellClient = null;
    let activeCallId = null;
    let activeCardElement = null;

    /**
     * Initialize Retell integration
     */
    function initRetellIntegration() {
        // Check if retellClientJsSdk is available (UMD global)
        if (typeof retellClientJsSdk === 'undefined' || !retellClientJsSdk.RetellWebClient) {
            console.error('Retell SDK not loaded. retellClientJsSdk:', typeof retellClientJsSdk);
            return;
        }

        // Initialize the client from the SDK namespace
        retellClient = new retellClientJsSdk.RetellWebClient();

        // Set up event listeners
        setupRetellEventListeners();

        // Bind click handlers to dashboard cards
        bindCardClickHandlers();

        console.log('Retell integration initialized');
    }

    /**
     * Set up Retell client event listeners
     */
    function setupRetellEventListeners() {
        retellClient.on('call_started', () => {
            console.log('Call started');
            updateCallUI('active');
        });

        retellClient.on('call_ended', () => {
            console.log('Call ended');
            updateCallUI('ended');
            cleanupCall();
        });

        retellClient.on('agent_start_talking', () => {
            updateCallUI('agent_talking');
        });

        retellClient.on('agent_stop_talking', () => {
            updateCallUI('active');
        });

        retellClient.on('error', (error) => {
            console.error('Retell error:', error);
            showCallError('Call error occurred. Please try again.');
            cleanupCall();
        });
    }

    /**
     * Bind click handlers to dashboard stat cards
     */
    function bindCardClickHandlers() {
        // Use event delegation for HTMX-loaded content
        document.body.addEventListener('click', function(e) {
            const card = e.target.closest('[data-retell-agent-id]');
            if (card && !card.classList.contains('retell-call-active')) {
                e.preventDefault();
                startCall(card);
            }
        });

        // Bind end call button handler
        document.body.addEventListener('click', function(e) {
            if (e.target.closest('.retell-end-call-btn')) {
                e.preventDefault();
                e.stopPropagation();
                endCall();
            }
        });
    }

    /**
     * Start a voice call with the agent
     * @param {HTMLElement} cardElement
     */
    async function startCall(cardElement) {
        if (activeCallId) {
            showCallError('A call is already in progress');
            return;
        }

        const agentId = cardElement.dataset.retellAgentId;
        if (!agentId) {
            showCallError('No agent configured for this card');
            return;
        }

        activeCardElement = cardElement;
        updateCallUI('connecting');

        try {
            // Request microphone permission first
            await navigator.mediaDevices.getUserMedia({ audio: true });

            // Create web call via our PHP endpoint
            const response = await fetch('/api/retell/create-web-call.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ agent_id: agentId }),
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || 'Failed to create call');
            }

            const data = await response.json();

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
                showCallError('Microphone access denied. Please allow microphone access to make calls.');
            } else {
                showCallError(error.message || 'Failed to start call');
            }

            cleanupCall();
        }
    }

    /**
     * End the current call
     */
    function endCall() {
        if (retellClient) {
            retellClient.stopCall();
        }
        cleanupCall();
    }

    /**
     * Clean up after call ends
     */
    function cleanupCall() {
        activeCallId = null;
        if (activeCardElement) {
            activeCardElement.classList.remove('retell-call-active', 'retell-call-connecting');
            removeEndCallButton(activeCardElement);
            activeCardElement = null;
        }
    }

    /**
     * Update UI based on call state
     * @param {string} state - connecting, active, agent_talking, ended
     */
    function updateCallUI(state) {
        if (!activeCardElement) return;

        switch (state) {
            case 'connecting':
                activeCardElement.classList.add('retell-call-connecting');
                activeCardElement.classList.remove('retell-call-active');
                showCallIndicator(activeCardElement, 'Connecting...');
                break;

            case 'active':
                activeCardElement.classList.remove('retell-call-connecting');
                activeCardElement.classList.add('retell-call-active');
                showCallIndicator(activeCardElement, 'Call Started');
                addEndCallButton(activeCardElement);
                break;

            case 'agent_talking':
                showCallIndicator(activeCardElement, 'Agent Speaking...');
                break;

            case 'ended':
                activeCardElement.classList.remove('retell-call-active', 'retell-call-connecting');
                hideCallIndicator(activeCardElement);
                removeEndCallButton(activeCardElement);
                break;
        }
    }

    /**
     * Show call indicator on card
     * @param {HTMLElement} card
     * @param {string} message
     */
    function showCallIndicator(card, message) {
        let indicator = card.querySelector('.retell-call-indicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.className = 'retell-call-indicator';
            indicator.id = 'retell-indicator-' + (card.id || Date.now());
            card.querySelector('.card-body').appendChild(indicator);
        }
        indicator.innerHTML = '<i class="feather-phone-call me-2"></i>' + message;
        indicator.style.display = 'flex';
    }

    /**
     * Hide call indicator
     * @param {HTMLElement} card
     */
    function hideCallIndicator(card) {
        const indicator = card.querySelector('.retell-call-indicator');
        if (indicator) {
            indicator.style.display = 'none';
        }
    }

    /**
     * Add end call button to card
     * @param {HTMLElement} card
     */
    function addEndCallButton(card) {
        if (card.querySelector('.retell-end-call-btn')) return;

        const btn = document.createElement('button');
        btn.className = 'btn btn-danger btn-sm retell-end-call-btn mt-2';
        btn.id = 'retell-end-btn-' + (card.id || Date.now());
        btn.innerHTML = '<i class="feather-phone-off me-1"></i> End Call';

        const cardBody = card.querySelector('.card-body');
        cardBody.appendChild(btn);
    }

    /**
     * Remove end call button
     * @param {HTMLElement} card
     */
    function removeEndCallButton(card) {
        const btn = card.querySelector('.retell-end-call-btn');
        if (btn) {
            btn.remove();
        }
    }

    /**
     * Show error message
     * @param {string} message
     */
    function showCallError(message) {
        // Use a simple alert for now, can be enhanced with toast/modal
        alert(message);
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRetellIntegration);
    } else {
        initRetellIntegration();
    }

    // Re-initialize after HTMX content swaps (for dynamically loaded dashboard)
    document.body.addEventListener('htmx:afterSwap', function(evt) {
        if (evt.detail.target.id === 'page-content') {
            // Cards are loaded via HTMX, handlers already bound via delegation
            console.log('Dashboard content loaded, Retell handlers ready');
        }
    });

})();
