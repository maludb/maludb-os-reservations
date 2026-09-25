<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Voice Agent Call</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .call-card {
            background: #fff;
            border-radius: 16px;
            padding: 3rem 2.5rem;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 4px 24px rgba(0,0,0,0.1);
        }
        .call-btn {
            background: #6366f1;
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 100px;
            height: 100px;
            font-size: 2.5rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .call-btn:hover { background: #4f46e5; transform: scale(1.05); }
        .call-btn:disabled { background: #9ca3af; cursor: not-allowed; transform: none; }
        .status { margin-top: 1.5rem; font-size: 1.1rem; color: #374151; font-weight: 500; }
        .timer { font-size: 2rem; font-weight: 600; color: #111; margin-top: 0.5rem; display: none; }
        .end-btn {
            background: #ef4444;
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: 0.75rem 2.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 1.5rem;
            display: none;
        }
        .end-btn:hover { background: #dc2626; }

        .pulse { animation: pulse 1.5s ease-in-out infinite; }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(99,102,241,0.4); }
            70% { box-shadow: 0 0 0 20px rgba(99,102,241,0); }
            100% { box-shadow: 0 0 0 0 rgba(99,102,241,0); }
        }
        .active { background: #22c55e; }
        .active:hover { background: #16a34a; }
        .talking { background: #3b82f6; animation: pulse 0.8s ease-in-out infinite; }
    </style>
</head>
<body>

<div class="call-card" id="call-card">
    <button class="call-btn" id="call-btn" onclick="startCall()">&#9742;</button>
    <div class="status" id="status">Click to call</div>
    <div class="timer" id="timer">00:00</div>
    <button class="end-btn" id="end-btn" onclick="endCall()">End Call</button>
</div>

<script src="assets/js/retell-sdk-bundle.min.js"></script>
<script>
(function() {
    var AGENT_ID = 'agent_34e6ac5fc8792f0d02ccea1e9a';
    var client = null;
    var startTime = null;
    var timerInt = null;

    function init() {
        if (typeof retellClientJsSdk === 'undefined' || !retellClientJsSdk.RetellWebClient) return;
        client = new retellClientJsSdk.RetellWebClient();

        client.on('call_started', function() {
            setStatus('active', 'Connected');
            document.getElementById('end-btn').style.display = 'inline-block';
            document.getElementById('timer').style.display = 'block';
            startTime = Date.now();
            timerInt = setInterval(tick, 1000);
        });
        client.on('call_ended', function() {
            setStatus('ended', 'Call ended');
            cleanup();
        });
        client.on('agent_start_talking', function() {
            document.getElementById('call-btn').className = 'call-btn talking';
            document.getElementById('status').textContent = 'Agent speaking...';
        });
        client.on('agent_stop_talking', function() {
            document.getElementById('call-btn').className = 'call-btn active';
            document.getElementById('status').textContent = 'Listening...';
        });
        client.on('error', function() {
            setStatus('ended', 'Call error');
            cleanup();
        });
    }

    function setStatus(state, msg) {
        var btn = document.getElementById('call-btn');
        document.getElementById('status').textContent = msg;
        if (state === 'connecting') { btn.className = 'call-btn pulse'; btn.disabled = true; }
        else if (state === 'active') { btn.className = 'call-btn active'; btn.disabled = true; }
        else { btn.className = 'call-btn'; btn.disabled = false; }
    }

    function tick() {
        var s = Math.floor((Date.now() - startTime) / 1000);
        document.getElementById('timer').textContent =
            String(Math.floor(s/60)).padStart(2,'0') + ':' + String(s%60).padStart(2,'0');
    }

    function cleanup() {
        if (timerInt) { clearInterval(timerInt); timerInt = null; }
        document.getElementById('end-btn').style.display = 'none';
        setTimeout(function() {
            document.getElementById('timer').style.display = 'none';
            setStatus('idle', 'Click to call');
        }, 2000);
    }

    window.startCall = async function() {
        if (!client) { alert('SDK loading, try again.'); return; }
        setStatus('connecting', 'Connecting...');
        try {
            await navigator.mediaDevices.getUserMedia({ audio: true });
            var r = await fetch('/api/retell/create-demo-call.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ agent_id: AGENT_ID })
            });
            if (!r.ok) { var e = await r.json(); throw new Error(e.error || 'Failed'); }
            var d = await r.json();
            await client.startCall({ accessToken: d.access_token });
        } catch(err) {
            setStatus('ended', err.name === 'NotAllowedError' ? 'Mic access denied' : err.message);
            cleanup();
        }
    };

    window.endCall = function() {
        if (client) client.stopCall();
        cleanup();
        setStatus('ended', 'Call ended');
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
</script>
</body>
</html>
