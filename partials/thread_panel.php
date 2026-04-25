<style>
  :root {
    --chat-bg: #eef1f5;
    --chat-surface: #ffffff;
    --chat-border: #d9dee7;
    --chat-text: #111827;
    --chat-muted: #6b7280;
    --chat-muted-2: #9ca3af;
    --chat-me: #0d6efd;
    --chat-other: #ffffff;
  }

  .chat-shell {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 56px);
    background: var(--chat-bg);
  }

  .chat-thread-header {
    background: rgba(255,255,255,0.92);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid var(--chat-border);
    padding: 10px 14px 8px;
    position: sticky;
    top: 0;
    z-index: 15;
  }

  .chat-thread-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--chat-text);
    line-height: 1.2;
  }

  .chat-thread-meta {
    font-size: 0.78rem;
    color: var(--chat-muted);
    margin-top: 2px;
  }

  .chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 10px 10px 20px;
    scroll-behavior: smooth;
  }

  .chat-day-separator {
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 14px 0 10px;
  }

  .chat-day-separator span {
    font-size: 0.74rem;
    color: var(--chat-muted);
    background: rgba(255,255,255,0.8);
    border: 1px solid var(--chat-border);
    border-radius: 999px;
    padding: 3px 10px;
    backdrop-filter: blur(4px);
  }

  .chat-row {
    display: flex;
    margin-bottom: 2px;
  }

  .chat-row.me {
    justify-content: flex-end;
  }

  .chat-row.other {
    justify-content: flex-start;
  }

  .chat-cluster {
    max-width: min(78%, 560px);
    display: flex;
    flex-direction: column;
  }

  .chat-row.me .chat-cluster {
    align-items: flex-end;
  }

  .chat-row.other .chat-cluster {
    align-items: flex-start;
  }

  .chat-author {
    font-size: 0.72rem;
    color: var(--chat-muted);
    margin: 8px 0 3px 8px;
    font-weight: 600;
    line-height: 1.2;
  }

  .chat-row.me .chat-author {
    display: none;
  }

  .chat-bubble {
    position: relative;
    display: inline-block;
    padding: 9px 12px;
    border-radius: 18px;
    font-size: 0.96rem;
    line-height: 1.34;
    word-break: break-word;
    white-space: pre-wrap;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    margin-top: 1px;
  }

  .chat-row.me .chat-bubble {
    background: var(--chat-me);
    color: #fff;
  }

  .chat-row.other .chat-bubble {
    background: var(--chat-other);
    color: var(--chat-text);
    border: 1px solid var(--chat-border);
  }

  /* bubble grouping shapes */
  .chat-bubble.single {
    border-radius: 18px;
  }

  .chat-row.me .chat-bubble.first {
    border-bottom-right-radius: 8px;
  }
  .chat-row.me .chat-bubble.middle {
    border-top-right-radius: 8px;
    border-bottom-right-radius: 8px;
    border-top-left-radius: 18px;
    border-bottom-left-radius: 18px;
  }
  .chat-row.me .chat-bubble.last {
    border-top-right-radius: 8px;
  }

  .chat-row.other .chat-bubble.first {
    border-bottom-left-radius: 8px;
  }
  .chat-row.other .chat-bubble.middle {
    border-top-left-radius: 8px;
    border-bottom-left-radius: 8px;
    border-top-right-radius: 18px;
    border-bottom-right-radius: 18px;
  }
  .chat-row.other .chat-bubble.last {
    border-top-left-radius: 8px;
  }

  .chat-time {
    font-size: 0.68rem;
    color: var(--chat-muted-2);
    margin: 3px 6px 8px;
    line-height: 1.1;
  }

  .chat-row.me .chat-time {
    text-align: right;
  }

  .chat-row.other .chat-time {
    text-align: left;
  }

  .chat-empty {
    color: var(--chat-muted);
    font-size: 0.92rem;
    text-align: center;
    padding: 36px 16px;
  }

  .chat-composer {
    position: sticky;
    bottom: 0;
    z-index: 20;
    background: rgba(255,255,255,0.94);
    backdrop-filter: blur(10px);
    border-top: 1px solid var(--chat-border);
    padding: 10px 10px calc(10px + env(safe-area-inset-bottom));
  }

  .chat-form {
    display: flex;
    gap: 8px;
    align-items: flex-end;
  }

  .chat-input-wrap {
    flex: 1;
    background: #fff;
    border: 1px solid var(--chat-border);
    border-radius: 22px;
    padding: 0;
    overflow: hidden;
  }

  .chat-input {
    border: 0;
    border-radius: 22px;
    min-height: 44px;
    max-height: 132px;
    resize: none;
    padding: 11px 14px;
    font-size: 0.95rem;
    line-height: 1.3;
    box-shadow: none !important;
  }

  .chat-send {
    border-radius: 999px;
    min-width: 58px;
    height: 44px;
    font-weight: 600;
    padding: 0 16px;
    flex-shrink: 0;
  }

/* Mobile-first sizing */
.chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 10px 6px 20px;
  scroll-behavior: smooth;
}

.chat-cluster {
  max-width: 92%;
}

.chat-bubble {
  padding: 12px 16px;
  font-size: 1.05rem;
  line-height: 1.4;
}

.chat-row {
  margin-bottom: 4px;
}

.chat-input {
  min-height: 48px;
  font-size: 1rem;
}

.chat-send {
  min-width: 60px;
  height: 48px;
}

@media (min-width: 768px) {
  .chat-shell {
    height: calc(100vh - 60px);
  }

  .chat-messages {
    padding-left: 18px;
    padding-right: 18px;
  }

  .chat-cluster {
    max-width: min(72%, 680px);
  }

  .chat-bubble {
    font-size: 0.98rem;
    padding: 10px 14px;
  }

  .chat-input {
    min-height: 44px;
    font-size: 0.95rem;
  }

  .chat-send {
    min-width: 58px;
    height: 44px;
  }
}
</style>

<div class="chat-shell">
  <div class="chat-thread-header">
    <div class="chat-thread-title"><?= htmlspecialchars($thread_title ?? 'Discussion') ?></div>
    <div class="chat-thread-meta" id="threadMeta">Loading...</div>
  </div>

  <div id="threadMessages" class="chat-messages">
    <div class="chat-empty">Loading discussion…</div>
  </div>

  <div class="chat-composer">
    <form id="threadForm" class="chat-form">
      <input type="hidden" id="threadId" value="<?= (int)$thread_id ?>">

      <div class="chat-input-wrap">
        <textarea
          id="threadInput"
          class="form-control chat-input"
          rows="1"
          placeholder="<?= htmlspecialchars($placeholder ?? 'Type a message...') ?>"
        ></textarea>
      </div>

      <button class="btn btn-primary chat-send" type="submit">Send</button>
    </form>
  </div>
</div>

<script>
(function(){
  const messagesEl = document.getElementById('threadMessages');
  const formEl = document.getElementById('threadForm');
  const inputEl = document.getElementById('threadInput');
  const metaEl = document.getElementById('threadMeta');
  const threadId = document.getElementById('threadId').value;
  let lastMessageSignature = '';
  let pollTimer = null;

  function esc(str) {
    return String(str || '').replace(/[&<>"']/g, m => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
    })[m]);
  }

  function parseDate(ts) {
    const d = new Date(String(ts).replace(' ', 'T') + 'Z');
    return isNaN(d.getTime()) ? null : d;
  }

  function dateKey(ts) {
    const d = parseDate(ts);
    if (!d) return 'unknown';
    return `${d.getFullYear()}-${d.getMonth()+1}-${d.getDate()}`;
  }

  function formatDayLabel(ts) {
    const d = parseDate(ts);
    if (!d) return '';
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);
    const dateOnly = new Date(d.getFullYear(), d.getMonth(), d.getDate());

    if (dateOnly.getTime() === today.getTime()) return 'Today';
    if (dateOnly.getTime() === yesterday.getTime()) return 'Yesterday';

    return d.toLocaleDateString([], {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    });
  }

  function formatTime(ts) {
    const d = parseDate(ts);
    if (!d) return String(ts || '');
    return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  }

  function clusterType(messages, i) {
    const current = messages[i];
    const prev = messages[i - 1];
    const next = messages[i + 1];

    const sameAsPrev =
      prev &&
      !!prev.me === !!current.me &&
      (prev.author || '') === (current.author || '') &&
      dateKey(prev.created_at) === dateKey(current.created_at);

    const sameAsNext =
      next &&
      !!next.me === !!current.me &&
      (next.author || '') === (current.author || '') &&
      dateKey(next.created_at) === dateKey(current.created_at);

    if (!sameAsPrev && !sameAsNext) return 'single';
    if (!sameAsPrev && sameAsNext) return 'first';
    if (sameAsPrev && sameAsNext) return 'middle';
    return 'last';
  }

  function shouldShowAuthor(messages, i) {
    const current = messages[i];
    const prev = messages[i - 1];
    if (!current || current.me) return false;
    if (!prev) return true;

    return !(
      !prev.me &&
      (prev.author || '') === (current.author || '') &&
      dateKey(prev.created_at) === dateKey(current.created_at)
    );
  }

  function shouldShowTime(messages, i) {
    const current = messages[i];
    const next = messages[i + 1];
    if (!next) return true;

    return !(
      !!next.me === !!current.me &&
      (next.author || '') === (current.author || '') &&
      dateKey(next.created_at) === dateKey(current.created_at)
    );
  }

  function renderMessage(m, shapeClass, showAuthor, showTime) {
    const isMe = !!m.me;
    const rowClass = isMe ? 'me' : 'other';

    return `
      <div class="chat-row ${rowClass}">
        <div class="chat-cluster">
          ${showAuthor ? `<div class="chat-author">${esc(m.author || '')}</div>` : ''}
          <div class="chat-bubble ${shapeClass}">${esc(m.body).replace(/\n/g,'<br>')}</div>
          ${showTime ? `<div class="chat-time">${esc(formatTime(m.created_at))}</div>` : ''}
        </div>
      </div>
    `;
  }

  function renderMessages(messages) {
    if (!messages.length) {
      return '<div class="chat-empty">No messages yet.</div>';
    }

    let html = '';
    let lastDayKey = null;

    messages.forEach((m, i) => {
      const currentDayKey = dateKey(m.created_at);
      if (currentDayKey !== lastDayKey) {
        html += `
          <div class="chat-day-separator">
            <span>${esc(formatDayLabel(m.created_at))}</span>
          </div>
        `;
        lastDayKey = currentDayKey;
      }

      html += renderMessage(
        m,
        clusterType(messages, i),
        shouldShowAuthor(messages, i),
        shouldShowTime(messages, i)
      );
    });

    return html;
  }

  function scrollToBottom(smooth = false) {
    messagesEl.scrollTo({
      top: messagesEl.scrollHeight,
      behavior: smooth ? 'smooth' : 'auto'
    });
  }

  function autoResizeInput() {
    inputEl.style.height = 'auto';
    inputEl.style.height = Math.min(inputEl.scrollHeight, 132) + 'px';
  }

async function markRead() {
  try {
    await fetch('api/mark_thread_read.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ thread_id: Number(threadId) })
    });
  } catch (e) {
    console.warn('Failed to mark thread read');
  }
}  

async function load(smooth = false, preserveIfReading = true){
  const nearBottom =
    (messagesEl.scrollHeight - messagesEl.scrollTop - messagesEl.clientHeight) < 120;

  const res = await fetch(`api/get_thread_messages.php?thread_id=${threadId}&_=${Date.now()}`, {
    cache: 'no-store'
  });
  const data = await res.json();

  if (!data.ok) {
    messagesEl.innerHTML = '<div class="chat-empty text-danger">Error loading messages.</div>';
    return;
  }

  const msgs = data.messages || [];
  const signature = msgs.map(m => `${m.created_at}|${m.author}|${m.body}`).join('||');

  if (signature === lastMessageSignature) {
    return;
  }

  lastMessageSignature = signature;
  messagesEl.innerHTML = renderMessages(msgs);
  metaEl.textContent = `${msgs.length} message${msgs.length === 1 ? '' : 's'}`;
  await markRead();

  if (smooth || nearBottom || !preserveIfReading) {
    scrollToBottom(smooth);
  }
}

  inputEl.addEventListener('input', autoResizeInput);

  inputEl.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      formEl.requestSubmit();
    }
  });

  formEl.addEventListener('submit', async function(e){
    e.preventDefault();

    const body = inputEl.value.trim();
    if (!body) return;

    const res = await fetch('api/post_thread_message.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ thread_id: threadId, body })
    });

    const data = await res.json();
    if (!data.ok) {
      alert(data.error || 'Failed to send message.');
      return;
    }

    inputEl.value = '';
    autoResizeInput();
    await load(true);
    inputEl.focus();
  });

autoResizeInput();
load(false, false);

pollTimer = setInterval(() => {
  load(false, true);
}, 5000);

window.addEventListener('beforeunload', function() {
  if (pollTimer) clearInterval(pollTimer);
});
})();
</script>