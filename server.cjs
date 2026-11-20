const http = require('http');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const querystring = require('querystring');
const { AsyncLocalStorage } = require('async_hooks');

const PORT = 3000;
const HOST = '0.0.0.0';
const UPLOADS_DIR = path.join(__dirname, 'uploads');
const STATE_FILE = path.join(__dirname, 'database', '.preview_state.json');
const SESSION_COOKIE = 'foundu_sid';
const SESSION_TTL_MS = 7 * 24 * 60 * 60 * 1000;
const sessions = new Map();
const sessionStore = new AsyncLocalStorage();

const mimeTypes = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.webp': 'image/webp',
  '.gif': 'image/gif',
  '.svg': 'image/svg+xml',
  '.sql': 'text/plain; charset=utf-8',
  '.md': 'text/markdown; charset=utf-8',
};

// In-memory store initialized with seed data matching database/lost_and_found_db.sql
const state = {
  settings: {
    system_name: 'Lost & Found Management System',
    organization_name: 'Polytechnic Campus Administration',
    items_per_page: '15',
  },
  categories: [
    { id: 1, name: 'Electronics' },
    { id: 2, name: 'Bags & Wallets' },
    { id: 3, name: 'Clothing & Accessories' },
    { id: 4, name: 'Books & Documents' },
    { id: 5, name: 'Keys & IDs' },
    { id: 6, name: 'Jewelry & Watches' },
    { id: 7, name: 'Sports Equipment' },
    { id: 8, name: 'Toys & Games' },
    { id: 9, name: 'Medicine & Health' },
    { id: 10, name: 'Others' },
  ],
  users: [
    {
      id: 1,
      full_name: 'System Administrator',
      username: 'admin',
      email: 'admin@foundu.edu',
      password: 'admin123',
      role: 'admin',
      status: 'active',
      last_login: '2026-11-18 09:30:00',
      created_at: '2026-11-01 08:00:00',
    },
    {
      id: 2,
      full_name: 'Campus Staff Officer',
      username: 'staff',
      email: 'staff@foundu.edu',
      password: 'staff123',
      role: 'staff',
      status: 'active',
      last_login: '2026-11-17 14:15:00',
      created_at: '2026-11-02 09:00:00',
    },
  ],
  lostItems: [
    {
      id: 1,
      item_name: 'MacBook Pro 14"',
      category_id: 1,
      description: 'Space Gray, sticker of campus tech club on back lid',
      date_lost: '2026-11-15',
      location_lost: 'Main Library - 2nd Floor',
      contact_info: 'john.student@foundu.edu',
      image_path: null,
      status: 'open',
      reported_by: 1,
      created_at: '2026-11-15 10:20:00',
      updated_at: null,
    },
    {
      id: 2,
      item_name: 'Brown Leather Bi-fold Wallet',
      category_id: 2,
      description: 'Contains student ID and transit card',
      date_lost: '2026-11-16',
      location_lost: 'University Cafeteria',
      contact_info: 'sarah.k@foundu.edu',
      image_path: null,
      status: 'open',
      reported_by: 1,
      created_at: '2026-11-16 13:45:00',
      updated_at: null,
    },
  ],
  foundItems: [
    {
      id: 1,
      item_name: 'Apple Laptop (MacBook)',
      category_id: 1,
      description: 'Space Gray MacBook found in library study lounge 4',
      date_found: '2026-11-15',
      location_found: 'Library 2nd Floor',
      storage_location: 'Security Locker #12',
      notes: 'Turned in by student assistant',
      image_path: null,
      status: 'available',
      recorded_by: 1,
      created_at: '2026-11-15 11:05:00',
      updated_at: null,
    },
    {
      id: 2,
      item_name: 'Blue Hydro Flask Bottle',
      category_id: 10,
      description: '32oz water bottle with mountain climbing stickers',
      date_found: '2026-11-17',
      location_found: 'Gymnasium Bleachers',
      storage_location: 'Storage Bin A-3',
      notes: 'Cleaned and safely stored',
      image_path: null,
      status: 'available',
      recorded_by: 1,
      created_at: '2026-11-17 16:10:00',
      updated_at: null,
    },
  ],
  matches: [],
  activityLogs: [
    {
      id: 1,
      user_id: 1,
      action: 'LOGIN',
      target_type: 'users',
      target_id: 1,
      details: 'User admin logged in successfully',
      ip_address: '127.0.0.1',
      created_at: '2026-11-18 09:30:00',
    },
  ],
  backupLogs: [],
  notifications: [],
  nextIds: { lost: 3, found: 3, match: 1, user: 3, log: 2, backup: 1, notification: 1 },
};

function loadState() {
  try {
    if (fs.existsSync(STATE_FILE)) {
      const raw = fs.readFileSync(STATE_FILE, 'utf8');
      const saved = JSON.parse(raw);
      if (saved && typeof saved === 'object') {
        if (saved.settings) {
          state.settings = { ...state.settings, ...saved.settings };
          if (state.settings.system_name === 'FoundU - Lost & Found Management System') {
            state.settings.system_name = 'Lost & Found Management System';
          }
        }
        if (Array.isArray(saved.users) && saved.users.length) state.users = saved.users;
        if (Array.isArray(saved.lostItems)) state.lostItems = saved.lostItems;
        if (Array.isArray(saved.foundItems)) state.foundItems = saved.foundItems;
        if (Array.isArray(saved.matches)) state.matches = saved.matches;
        if (Array.isArray(saved.activityLogs)) state.activityLogs = saved.activityLogs;
        if (Array.isArray(saved.backupLogs)) state.backupLogs = saved.backupLogs;
        if (Array.isArray(saved.notifications)) state.notifications = saved.notifications;
        if (saved.nextIds) state.nextIds = { ...state.nextIds, ...saved.nextIds };
        if (saved.sessions && typeof saved.sessions === 'object') {
          const now = Date.now();
          for (const [sid, s] of Object.entries(saved.sessions)) {
            if (s && typeof s === 'object' && (!s.expiresAt || s.expiresAt > now)) {
              sessions.set(sid, {
                id: sid,
                userId: s.userId || null,
                flash: Array.isArray(s.flash) ? s.flash : [],
                expiresAt: s.expiresAt || (now + SESSION_TTL_MS),
              });
            }
          }
        }
      }
    }
  } catch (err) {
    console.error('Failed to load preview state:', err.message);
  }
}

function saveState() {
  try {
    const dir = path.dirname(STATE_FILE);
    if (!fs.existsSync(dir)) {
      fs.mkdirSync(dir, { recursive: true });
    }
    const now = Date.now();
    const persistedSessions = {};
    for (const [sid, s] of sessions.entries()) {
      if (s && s.userId && (!s.expiresAt || s.expiresAt > now)) {
        persistedSessions[sid] = {
          userId: s.userId,
          flash: Array.isArray(s.flash) ? s.flash : [],
          expiresAt: s.expiresAt || (now + SESSION_TTL_MS),
        };
      }
    }
    const snapshot = {
      settings: state.settings,
      users: state.users,
      lostItems: state.lostItems,
      foundItems: state.foundItems,
      matches: state.matches,
      activityLogs: state.activityLogs,
      backupLogs: state.backupLogs,
      notifications: state.notifications,
      nextIds: state.nextIds,
      sessions: persistedSessions,
    };
    fs.writeFileSync(STATE_FILE, JSON.stringify(snapshot, null, 2), 'utf8');
  } catch (err) {
    console.error('Failed to save preview state:', err.message);
  }
}

loadState();

function nowStr() {
  const d = new Date();
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
}

function todayStr() {
  return nowStr().slice(0, 10);
}

function esc(val) {
  return String(val ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function fmtDate(str) {
  if (!str) return '—';
  const d = new Date(str.length === 10 ? str + 'T00:00:00' : str.replace(' ', 'T'));
  if (isNaN(d.getTime())) return esc(str);
  return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
}

function fmtDateTime(str) {
  if (!str) return '—';
  const d = new Date(str.replace(' ', 'T'));
  if (isNaN(d.getTime())) return esc(str);
  return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }) +
    ' ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

function parseCookies(req) {
  const header = req.headers.cookie || '';
  const out = {};
  for (const part of header.split(';')) {
    const idx = part.indexOf('=');
    if (idx === -1) continue;
    const k = part.slice(0, idx).trim();
    const v = part.slice(idx + 1).trim();
    if (k) out[k] = decodeURIComponent(v);
  }
  return out;
}

function isHttpsRequest(req) {
  const fwdProto = String(req.headers['x-forwarded-proto'] || '').toLowerCase();
  const forwarded = String(req.headers.forwarded || '').toLowerCase();
  const origin = String(req.headers.origin || '').toLowerCase();
  const referer = String(req.headers.referer || '').toLowerCase();
  const host = String(req.headers.host || '').toLowerCase();
  return !!(
    req.socket.encrypted ||
    fwdProto.includes('https') ||
    forwarded.includes('proto=https') ||
    origin.startsWith('https://') ||
    referer.startsWith('https://') ||
    host.includes('.run.app')
  );
}

function buildSessionCookie(req, sid) {
  const maxAgeSec = Math.floor(SESSION_TTL_MS / 1000);
  if (isHttpsRequest(req)) {
    return `${SESSION_COOKIE}=${sid}; Path=/; Max-Age=${maxAgeSec}; HttpOnly; SameSite=None; Secure; Partitioned`;
  }
  return `${SESSION_COOKIE}=${sid}; Path=/; Max-Age=${maxAgeSec}; HttpOnly; SameSite=Lax`;
}

function getOrCreateSession(req, res) {
  const cookies = parseCookies(req);
  let sid = cookies[SESSION_COOKIE];
  let sess = sid && sessions.get(sid);
  const now = Date.now();
  if (sess && sess.expiresAt && sess.expiresAt <= now) {
    sessions.delete(sid);
    sess = null;
  }
  if (!sess) {
    sid = crypto.randomBytes(24).toString('hex');
    sess = { id: sid, userId: null, flash: [], expiresAt: now + SESSION_TTL_MS };
    sessions.set(sid, sess);
    res.setHeader('Set-Cookie', buildSessionCookie(req, sid));
  } else {
    sess.expiresAt = now + SESSION_TTL_MS;
  }
  return sess;
}

function currentSession() {
  return sessionStore.getStore() || null;
}

function currentUser() {
  const sess = currentSession();
  if (!sess || !sess.userId) return null;
  return state.users.find((u) => u.id === sess.userId && u.status === 'active') || null;
}

function isAdmin() {
  const u = currentUser();
  return !!(u && u.role === 'admin');
}

function setFlash(type, message) {
  const sess = currentSession();
  if (sess) {
    sess.flash.push({ type, message });
  }
}

function renderFlash() {
  const sess = currentSession();
  if (!sess || !sess.flash.length) return '';
  const items = sess.flash.splice(0, sess.flash.length);
  let html = '<div class="flash-messages-container mb-4">';
  for (const msg of items) {
    let alertClass = 'alert-info';
    let iconClass = 'fa-circle-info';
    if (msg.type === 'success') { alertClass = 'alert-success'; iconClass = 'fa-circle-check'; }
    else if (msg.type === 'error' || msg.type === 'danger') { alertClass = 'alert-danger'; iconClass = 'fa-triangle-exclamation'; }
    else if (msg.type === 'warning') { alertClass = 'alert-warning'; iconClass = 'fa-circle-exclamation'; }
    html += `<div class="alert ${alertClass} alert-dismissible fade show d-flex align-items-center" role="alert">
      <i class="fa-solid ${iconClass} me-2 fs-5"></i>
      <div>${esc(msg.message)}</div>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>`;
  }
  html += '</div>';
  return html;
}

function logActivity(action, targetType, targetId, details) {
  const u = currentUser();
  state.activityLogs.unshift({
    id: state.nextIds.log++,
    user_id: u ? u.id : null,
    action,
    target_type: targetType,
    target_id: targetId,
    details,
    ip_address: '127.0.0.1',
    created_at: nowStr(),
  });
}

function createRoleItemNotifications(senderUser, relatedItemType, relatedItemId) {
  if (!senderUser || senderUser.status !== 'active') return 0;
  if (!['lost_item', 'found_item'].includes(relatedItemType) || !relatedItemId) return 0;

  const senderRole = senderUser.role === 'admin' ? 'admin' : 'staff';
  const targetRole = senderRole === 'staff' ? 'admin' : 'staff';
  const senderName = (senderUser.full_name || '').trim() || (senderRole === 'admin' ? 'System Administrator' : 'Campus Staff Officer');
  const itemLabel = relatedItemType === 'lost_item' ? 'Lost Item' : 'Found Item';

  const recipients = state.users.filter(
    (u) => u.role === targetRole && u.status === 'active' && u.id !== senderUser.id
  );
  if (!recipients.length) return 0;

  let created = 0;
  for (const recipient of recipients) {
    const duplicate = state.notifications.some(
      (n) =>
        n.recipient_user_id === recipient.id &&
        n.sender_user_id === senderUser.id &&
        n.related_item_type === relatedItemType &&
        n.related_item_id === relatedItemId
    );
    if (duplicate) continue;

    state.notifications.unshift({
      id: state.nextIds.notification++,
      recipient_user_id: recipient.id,
      sender_user_id: senderUser.id,
      sender_role: senderRole,
      related_item_id: relatedItemId,
      related_item_type: relatedItemType,
      title: 'New Item Added',
      message: `${senderName} has added a new ${itemLabel}.`,
      is_read: 0,
      read_at: null,
      created_at: nowStr(),
    });
    created++;
  }
  return created;
}

function getUnreadNotificationCount(userId) {
  return state.notifications.filter((n) => n.recipient_user_id === userId && !n.is_read).length;
}

function formatNotificationPayload(n) {
  const sender = state.users.find((u) => u.id === n.sender_user_id);
  const isFound = n.related_item_type === 'found_item';
  const item = isFound
    ? state.foundItems.find((f) => f.id === n.related_item_id)
    : state.lostItems.find((l) => l.id === n.related_item_id);

  return {
    id: n.id,
    title: n.title,
    message: n.message,
    sender_name: sender ? sender.full_name : 'Operator',
    sender_role: n.sender_role === 'admin' ? 'admin' : 'staff',
    related_item_id: n.related_item_id,
    related_item_type: isFound ? 'found_item' : 'lost_item',
    related_item_label: isFound ? 'Found Item' : 'Lost Item',
    related_item_name: item ? item.item_name : `Item #${n.related_item_id}`,
    item_exists: !!item,
    is_read: !!n.is_read,
    created_at: n.created_at,
    created_at_formatted: fmtDateTime(n.created_at),
    open_url: `/notifications/index.php?open=${n.id}`,
  };
}

function getUserNotifications(userId, limit = 100, afterId = 0) {
  return state.notifications
    .filter((n) => n.recipient_user_id === userId && (afterId <= 0 || n.id > afterId))
    .sort((a, b) => (b.id - a.id))
    .slice(0, limit)
    .map(formatNotificationPayload);
}

function getCategoryName(catId) {
  const c = state.categories.find((x) => x.id === Number(catId));
  return c ? c.name : 'General';
}

function getUserName(userId, fallback = 'Staff') {
  const u = state.users.find((x) => x.id === Number(userId));
  return u ? u.full_name : fallback;
}

function redirect(res, location) {
  res.writeHead(302, { Location: location });
  res.end();
}

function parseMultipart(buf, boundary) {
  const fields = {};
  const files = {};
  const delim = Buffer.from('--' + boundary);
  const headerSep = Buffer.from('\r\n\r\n');
  let pos = buf.indexOf(delim);

  while (pos !== -1) {
    let start = pos + delim.length;
    if (buf[start] === 0x2d && buf[start + 1] === 0x2d) {
      break; // closing boundary '--'
    }
    if (buf[start] === 0x0d && buf[start + 1] === 0x0a) {
      start += 2;
    }
    const nextPos = buf.indexOf(delim, start);
    if (nextPos === -1) break;

    let partBuf = buf.slice(start, nextPos);
    if (partBuf.length >= 2 && partBuf[partBuf.length - 2] === 0x0d && partBuf[partBuf.length - 1] === 0x0a) {
      partBuf = partBuf.slice(0, partBuf.length - 2);
    }

    const sepIdx = partBuf.indexOf(headerSep);
    if (sepIdx !== -1) {
      const headerText = partBuf.slice(0, sepIdx).toString('utf8');
      const bodyBuf = partBuf.slice(sepIdx + 4);
      const nameMatch = headerText.match(/name="([^"]+)"/i);
      const fileMatch = headerText.match(/filename="([^"]*)"/i);
      const typeMatch = headerText.match(/Content-Type:\s*([^\r\n;]+)/i);

      if (nameMatch) {
        const fieldName = nameMatch[1];
        if (!fileMatch) {
          fields[fieldName] = bodyBuf.toString('utf8');
        } else {
          const filename = fileMatch[1] || '';
          const contentType = typeMatch ? typeMatch[1].trim().toLowerCase() : '';
          if (filename !== '' || bodyBuf.length > 0) {
            files[fieldName] = {
              filename,
              contentType,
              buffer: bodyBuf,
              size: bodyBuf.length,
            };
          }
        }
      }
    }

    pos = nextPos;
  }

  fields._files = files;
  return fields;
}

function parseBody(req) {
  return new Promise((resolve) => {
    const chunks = [];
    req.on('data', (chunk) => chunks.push(chunk));
    req.on('end', () => {
      const buf = Buffer.concat(chunks);
      const contentType = req.headers['content-type'] || '';
      if (contentType.includes('multipart/form-data')) {
        const match = contentType.match(/boundary=(?:"([^"]+)"|([^;]+))/i);
        const boundary = match ? (match[1] || match[2]).trim() : '';
        if (boundary) {
          resolve(parseMultipart(buf, boundary));
          return;
        }
        resolve({ _files: {} });
      } else {
        const parsed = querystring.parse(buf.toString('utf8'));
        parsed._files = {};
        resolve(parsed);
      }
    });
  });
}

function detectImageExtension(buf) {
  if (!buf || buf.length < 12) return null;
  // JPEG: FF D8 FF
  if (buf[0] === 0xff && buf[1] === 0xd8 && buf[2] === 0xff) return 'jpg';
  // PNG: 89 50 4E 47 0D 0A 1A 0A
  if (
    buf[0] === 0x89 && buf[1] === 0x50 && buf[2] === 0x4e && buf[3] === 0x47 &&
    buf[4] === 0x0d && buf[5] === 0x0a && buf[6] === 0x1a && buf[7] === 0x0a
  ) return 'png';
  // WEBP: RIFF....WEBP
  if (buf.slice(0, 4).toString('ascii') === 'RIFF' && buf.slice(8, 12).toString('ascii') === 'WEBP') {
    return 'webp';
  }
  return null;
}

function saveUploadedImage(fileObj) {
  if (!fileObj || (!fileObj.filename && !fileObj.size)) {
    return { ok: true, path: null };
  }
  const MAX_BYTES = 5 * 1024 * 1024;
  if (!fileObj.buffer || fileObj.size <= 0) {
    return { ok: false, error: 'Uploaded image file is empty. Please choose a valid JPG, JPEG, PNG, or WEBP photo.' };
  }
  if (fileObj.size > MAX_BYTES) {
    return { ok: false, error: 'Image file exceeds the 5MB upload limit. Please select a smaller file.' };
  }

  const extFromName = path.extname(fileObj.filename || '').toLowerCase().replace('.', '');
  const allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
  if (!allowedExts.includes(extFromName)) {
    return { ok: false, error: 'Unsupported image format. Allowed formats: JPG, JPEG, PNG, WEBP (Max 5MB).' };
  }

  const allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (fileObj.contentType && fileObj.contentType !== 'application/octet-stream' && !allowedMimes.includes(fileObj.contentType)) {
    return { ok: false, error: 'Unsupported image content type. Allowed formats: JPG, JPEG, PNG, WEBP (Max 5MB).' };
  }

  const detectedExt = detectImageExtension(fileObj.buffer);
  if (!detectedExt) {
    return { ok: false, error: 'Invalid or corrupted image file. Allowed formats: JPG, JPEG, PNG, WEBP (Max 5MB).' };
  }

  if (!fs.existsSync(UPLOADS_DIR)) {
    fs.mkdirSync(UPLOADS_DIR, { recursive: true });
  }
  const filename = `item_${Date.now()}_${crypto.randomBytes(6).toString('hex')}.${detectedExt}`;
  const targetPath = path.join(UPLOADS_DIR, filename);
  fs.writeFileSync(targetPath, fileObj.buffer);
  return { ok: true, path: `uploads/${filename}` };
}

function deleteUploadedImage(relPath) {
  if (!relPath) return;
  try {
    const cleanRel = String(relPath).replace(/^\/+/, '');
    const targetPath = path.resolve(__dirname, cleanRel);
    const uploadsRoot = path.resolve(UPLOADS_DIR) + path.sep;
    if (targetPath.startsWith(uploadsRoot) && fs.existsSync(targetPath) && fs.statSync(targetPath).isFile()) {
      fs.unlinkSync(targetPath);
    }
  } catch (err) {
    console.error('Failed to delete uploaded image:', err.message);
  }
}

function imageFileExists(relPath) {
  if (!relPath) return false;
  try {
    const cleanRel = String(relPath).replace(/^\/+/, '');
    const targetPath = path.resolve(__dirname, cleanRel);
    const uploadsRoot = path.resolve(UPLOADS_DIR) + path.sep;
    return targetPath.startsWith(uploadsRoot) && fs.existsSync(targetPath) && fs.statSync(targetPath).isFile();
  } catch (e) {
    return false;
  }
}

function renderItemThumb(item, fallbackIcon = 'fa-regular fa-image') {
  if (item && imageFileExists(item.image_path)) {
    return `<img src="/${esc(item.image_path)}" alt="${esc(item.item_name)}" class="item-thumb" data-fallback-kind="thumb" data-fallback-icon="${esc(fallbackIcon)}">`;
  }
  return `<div class="item-thumb-placeholder"><i class="${esc(fallbackIcon)}"></i></div>`;
}

function renderItemDetailMedia(item, emptyIcon = 'fa-regular fa-image', emptyText = 'No image uploaded for this item') {
  if (item && imageFileExists(item.image_path)) {
    return `<img src="/${esc(item.image_path)}" alt="${esc(item.item_name)}" class="item-detail-image" data-fallback-kind="detail" data-fallback-icon="${esc(emptyIcon)}" data-fallback-text="${esc(emptyText)}">`;
  }
  return `<div class="bg-light text-muted p-5 text-center">
      <i class="${esc(emptyIcon)} fs-1 d-block mb-2 text-secondary"></i>
      <span class="font-meta small">${esc(emptyText)}</span>
  </div>`;
}

function renderEditImageSection(item, colClass = 'col-12 col-md-6') {
  const hasExisting = !!(item && imageFileExists(item.image_path));
  const existingSrc = hasExisting ? `/${esc(item.image_path)}` : '';
  const existingName = hasExisting ? esc(path.basename(item.image_path)) : '';
  return `<div class="${colClass}">
      <label for="itemImage" class="form-label small">Replace Photo (Optional)</label>
      <input type="file" class="form-control" id="itemImage" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
      <div class="form-text small">Accepted formats: JPG, JPEG, PNG, WEBP (Max 5MB).</div>
      <div class="image-upload-preview-card mt-2 ${hasExisting ? '' : 'd-none'}"
           data-image-preview-box
           data-existing-src="${existingSrc}"
           data-existing-name="${existingName}">
          ${hasExisting ? `
          <img src="${existingSrc}" alt="${esc(item.item_name)}" class="item-edit-preview" data-fallback-kind="edit">
          <div class="min-w-0">
              <div class="fw-semibold text-dark small text-truncate">${existingName}</div>
              <div class="font-meta text-muted small">Current uploaded photo &middot; Leave empty to keep</div>
          </div>` : ''}
      </div>
  </div>`;
}

function assetUrl(relPath) {
  const clean = String(relPath || '').replace(/^\/+/, '');
  const fullPath = path.join(__dirname, clean);
  let v = '';
  try {
    const st = fs.statSync(fullPath);
    if (st.isFile()) {
      v = `?v=${Math.floor(st.mtimeMs)}`;
    }
  } catch (_) {}
  return `/${clean}${v}`;
}

function renderLayout({ title, activeSection, bodyHtml, extraCss = [] }) {
  const u = currentUser();
  const pageTitle = title ? `${esc(title)} - FoundU` : 'FoundU - Lost & Found Management System';
  const unreadNotificationsCount = u ? getUnreadNotificationCount(u.id) : 0;

  const extraLinks = extraCss.map((c) => `<link rel="stylesheet" href="${assetUrl(c)}">`).join('\n    ');

  const navbarHtml = u ? `
    <nav class="navbar navbar-light sticky-top main-navbar">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center gap-2 text-decoration-none py-1" href="/dashboard.php">
                <span class="foundu-brand-mark" aria-hidden="true">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </span>
                <span class="brand-wordmark">FoundU</span>
            </a>

            <div class="ms-auto d-flex align-items-center">
                <div class="nav-icon-group">
                    <a href="/notifications/index.php"
                       class="nav-icon-btn nav-icon-bell ${activeSection === 'notifications' ? 'active' : ''}"
                       id="navNotificationsBtn"
                       data-bs-toggle="tooltip"
                       data-bs-placement="bottom"
                       title="Notifications"
                       aria-label="Notifications">
                        <i class="fa-solid fa-bell"></i>
                        <span class="nav-unread-badge ${unreadNotificationsCount > 0 ? '' : 'd-none'}" id="navNotificationBadge">
                            ${unreadNotificationsCount > 99 ? '99+' : unreadNotificationsCount}
                        </span>
                    </a>

                    <a href="/profile/index.php"
                       class="nav-icon-btn nav-icon-profile ${activeSection === 'profile' ? 'active' : ''}"
                       id="navProfileBtn"
                       data-bs-toggle="tooltip"
                       data-bs-placement="bottom"
                       title="My Profile"
                       aria-label="My Profile">
                        <i class="fa-solid fa-user"></i>
                    </a>

                    <button type="button"
                            class="nav-icon-btn nav-icon-logout"
                            id="navLogoutBtn"
                            data-logout-trigger
                            data-bs-toggle="tooltip"
                            data-bs-placement="bottom"
                            title="Logout"
                            aria-label="Logout">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>` : '';

  const sidebarHtml = u ? `
    <aside class="sidebar-wrapper py-4 px-3 d-flex flex-column">
        <div class="sidebar-heading">
            Core Navigation
        </div>
        <ul class="nav nav-pills flex-column mb-3">
            <li class="nav-item">
                <a href="/dashboard.php" class="nav-link ${activeSection === 'dashboard' ? 'active' : ''}">
                    <i class="fa-solid fa-gauge-high fa-fw"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/lost_items/index.php" class="nav-link ${activeSection === 'lost_items' ? 'active' : ''}">
                    <i class="fa-solid fa-circle-question fa-fw"></i>
                    <span>Lost Items</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/found_items/index.php" class="nav-link ${activeSection === 'found_items' ? 'active' : ''}">
                    <i class="fa-solid fa-box-open fa-fw"></i>
                    <span>Found Items</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/matches/index.php" class="nav-link ${activeSection === 'matches' ? 'active' : ''}">
                    <i class="fa-solid fa-link fa-fw"></i>
                    <span>Matches &amp; Claims</span>
                </a>
            </li>
        </ul>

        ${u.role === 'admin' ? `
        <div class="sidebar-divider"></div>
        <div class="sidebar-heading">
            Administration
        </div>
        <ul class="nav nav-pills flex-column mb-0">
            <li class="nav-item">
                <a href="/admin/manage_users.php" class="nav-link ${activeSection === 'manage_users' ? 'active' : ''}">
                    <i class="fa-solid fa-users fa-fw"></i>
                    <span>Manage Users</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/admin/activity_log.php" class="nav-link ${activeSection === 'activity_log' ? 'active' : ''}">
                    <i class="fa-solid fa-clock-rotate-left fa-fw"></i>
                    <span>Activity Logs</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/admin/backup_recovery.php" class="nav-link ${activeSection === 'backup_recovery' ? 'active' : ''}">
                    <i class="fa-solid fa-database fa-fw"></i>
                    <span>Backup &amp; Recovery</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/admin/system_monitor.php" class="nav-link ${activeSection === 'system_monitor' ? 'active' : ''}">
                    <i class="fa-solid fa-microchip fa-fw"></i>
                    <span>System Monitor</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/admin/settings.php" class="nav-link ${activeSection === 'settings' ? 'active' : ''}">
                    <i class="fa-solid fa-sliders fa-fw"></i>
                    <span>System Settings</span>
                </a>
            </li>
        </ul>` : ''}
    </aside>` : '';

  const mainShell = u ? `
    <div class="d-flex flex-grow-1 flex-column flex-lg-row">
        ${sidebarHtml}
        <main class="main-content flex-grow-1 p-3 p-md-4 p-xl-5">
            ${bodyHtml}
        </main>
    </div>` : bodyHtml;

  const footerHtml = u ? `
    <footer class="app-footer py-3 mt-auto">
        <div class="container-fluid px-4 text-center text-md-start d-md-flex align-items-center justify-content-between">
            <span class="text-muted small">&copy; 2026 FoundU Development Team. All rights reserved.</span>
            <div class="footer-links mt-2 mt-md-0">
                <span class="text-muted small">FoundU &middot; Lost &amp; Found Management System</span>
            </div>
        </div>
    </footer>` : '';

  const modalsHtml = u ? `
    <div class="modal fade" id="logoutConfirmModal" tabindex="-1" aria-labelledby="logoutConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-0 pb-1">
                    <h5 class="modal-title font-display fw-semibold text-dark d-flex align-items-center gap-2" id="logoutConfirmModalLabel">
                        <i class="fa-solid fa-right-from-bracket text-danger"></i>
                        <span>Confirm Logout</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-0">Are you sure you want to log out of your FoundU operator session?</p>
                </div>
                <div class="modal-footer border-0 pt-2">
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                    <a href="/logout.php" class="btn btn-danger btn-sm px-3" id="confirmLogoutLink">Logout</a>
                </div>
            </div>
        </div>
    </div>
    <div id="notificationToastContainer" class="notification-toast-container" aria-live="polite" aria-atomic="true"></div>` : '';

  return `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${pageTitle}</title>
    <meta name="description" content="FoundU Campus Lost and Found Management System">
    <link rel="icon" type="image/png" href="${assetUrl('assets/aistudio/image_8911a931.png')}">
    <link rel="shortcut icon" type="image/png" href="${assetUrl('assets/aistudio/image_8911a931.png')}">
    <link rel="apple-touch-icon" href="${assetUrl('assets/aistudio/image_8911a931.png')}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="${assetUrl('assets/css/style.css')}">
    <link rel="stylesheet" href="${assetUrl('assets/css/forms.css')}">
    <link rel="stylesheet" href="${assetUrl('assets/css/responsive.css')}">
    ${extraLinks}
</head>
<body class="bg-light"${u ? ` data-user-id="${u.id}" data-notifications-api="/notifications/api.php"` : ''}>
<div class="app-wrapper d-flex flex-column min-vh-100">
    ${navbarHtml}
    ${mainShell}
    ${footerHtml}
</div>
${modalsHtml}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="${assetUrl('assets/js/main.js')}"></script>
</body>
</html>`;
}

function renderLoginPage(error = '', usernameVal = '') {
  const bodyHtml = `
    <div class="auth-page d-flex align-items-center justify-content-center min-vh-100 py-5 bg-gradient-navy">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-sm-10 col-md-8 col-lg-5 col-xl-4">
                    <div class="card auth-card border-0 overflow-hidden">
                        <div class="card-body p-4 p-sm-5">
                            <div class="text-center mb-4">
                                <div class="auth-brand-icon mx-auto mb-3 d-flex align-items-center justify-content-center">
                                    <i class="fa-solid fa-magnifying-glass fs-3"></i>
                                </div>
                                <h3 class="auth-brand-title mb-1">FoundU</h3>
                                <p class="font-meta text-muted small mb-0">${esc(state.settings.system_name)}</p>
                            </div>

                            ${renderFlash()}
                            ${error ? `
                            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
                                <i class="fa-solid fa-triangle-exclamation me-2 fs-5"></i>
                                <div class="small">${esc(error)}</div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>` : ''}

                            <form method="POST" action="/login.php" class="needs-validation" novalidate>
                                <div class="mb-3">
                                    <label for="usernameInput" class="form-label small fw-medium">Username</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0 text-muted">
                                            <i class="fa-solid fa-user"></i>
                                        </span>
                                        <input type="text" class="form-control bg-light border-start-0" id="usernameInput" name="username" value="${esc(usernameVal)}" placeholder="Enter your username" required autofocus>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="passwordInput" class="form-label small fw-medium">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0 text-muted">
                                            <i class="fa-solid fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control bg-light border-start-0" id="passwordInput" name="password" placeholder="Enter your password" required>
                                    </div>
                                </div>

                                <div class="d-grid mb-3">
                                    <button type="submit" class="btn btn-primary py-2 fw-semibold">
                                        <span>Login</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>`;
  return renderLayout({ title: 'Sign In', activeSection: 'login', bodyHtml, extraCss: ['assets/css/auth.css'] });
}

function renderDashboardPage() {
  const u = currentUser();
  const countLostOpen = state.lostItems.filter((i) => i.status === 'open').length;
  const countFoundAvail = state.foundItems.filter((i) => i.status === 'available').length;
  const countMatches = state.matches.length;
  const countUsers = state.users.filter((x) => x.status === 'active').length;
  const recentLost = state.lostItems.slice(0, 5);
  const recentFound = state.foundItems.slice(0, 5);

  const bodyHtml = `
    <div class="container-fluid">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
            <div>
                <h1 class="h3 fw-semibold mb-1">Welcome back, ${esc(u.full_name)}!</h1>
                <p class="font-meta text-muted small mb-0">Overview of campus lost &amp; found belongings, ongoing matches, and locker inventory.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="/lost_items/report.php" class="btn btn-outline-primary btn-sm px-3 py-2">
                    <i class="fa-solid fa-plus"></i>
                    <span>Report Lost</span>
                </a>
                <a href="/found_items/add.php" class="btn btn-primary btn-sm px-3 py-2">
                    <i class="fa-solid fa-box-open"></i>
                    <span>Record Found</span>
                </a>
            </div>
        </div>

        ${renderFlash()}

        <div class="row g-3 g-xl-4 mb-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card stat-card h-100">
                    <div class="card-body p-4 d-flex align-items-center justify-content-between gap-3">
                        <div>
                            <span class="stat-eyebrow">Open Lost Items</span>
                            <div class="stat-value stat-value-warning">${countLostOpen}</div>
                            <span class="stat-subtext">Awaiting matches</span>
                        </div>
                        <div class="stat-icon-wrapper stat-icon-warning">
                            <i class="fa-solid fa-circle-question"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card stat-card h-100">
                    <div class="card-body p-4 d-flex align-items-center justify-content-between gap-3">
                        <div>
                            <span class="stat-eyebrow">Available Found Items</span>
                            <div class="stat-value stat-value-success">${countFoundAvail}</div>
                            <span class="stat-subtext">Stored in security lockers</span>
                        </div>
                        <div class="stat-icon-wrapper stat-icon-success">
                            <i class="fa-solid fa-box-open"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card stat-card h-100">
                    <div class="card-body p-4 d-flex align-items-center justify-content-between gap-3">
                        <div>
                            <span class="stat-eyebrow">Confirmed Matches</span>
                            <div class="stat-value stat-value-primary">${countMatches}</div>
                            <span class="stat-subtext">Returned or linked</span>
                        </div>
                        <div class="stat-icon-wrapper stat-icon-primary">
                            <i class="fa-solid fa-link"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card stat-card h-100">
                    <div class="card-body p-4 d-flex align-items-center justify-content-between gap-3">
                        <div>
                            <span class="stat-eyebrow">Active Operators</span>
                            <div class="stat-value stat-value-muted">${countUsers}</div>
                            <span class="stat-subtext">Staff &amp; Administrators</span>
                        </div>
                        <div class="stat-icon-wrapper stat-icon-secondary">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-lg-6">
                <div class="card h-100 overflow-hidden">
                    <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
                        <h5 class="mb-0 d-flex align-items-center gap-2 fs-6">
                            <i class="fa-solid fa-circle-question text-primary"></i>
                            <span class="font-display">Recent Lost Items</span>
                        </h5>
                        <a href="/lost_items/index.php" class="btn btn-sm btn-neutral">View All</a>
                    </div>
                    <div class="card-body p-0">
                        ${!recentLost.length ? `
                        <div class="text-center py-5 text-muted">
                            <i class="fa-solid fa-inbox fs-2"></i>
                            <p class="small mt-2 mb-0">No lost items reported yet.</p>
                        </div>` : `
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Item</th>
                                        <th>Category</th>
                                        <th>Date Lost</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${recentLost.map((item) => `
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-3">
                                                ${renderItemThumb(item, 'fa-regular fa-image')}
                                                <div>
                                                    <div class="fw-semibold text-dark">${esc(item.item_name)}</div>
                                                    <small class="font-meta text-muted">${esc(item.location_lost)}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="category-badge">${esc(getCategoryName(item.category_id))}</span></td>
                                        <td class="small font-meta text-muted text-nowrap">${fmtDate(item.date_lost)}</td>
                                        <td><span class="badge status-badge-${esc(item.status)}">${esc(item.status.toUpperCase())}</span></td>
                                        <td class="text-end pe-4">
                                            <a href="/lost_items/view.php?id=${item.id}" class="btn btn-view btn-action-icon" title="View Details">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>`).join('')}
                                </tbody>
                            </table>
                        </div>`}
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="card h-100 overflow-hidden">
                    <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between">
                        <h5 class="mb-0 d-flex align-items-center gap-2 fs-6">
                            <i class="fa-solid fa-box-open text-primary"></i>
                            <span class="font-display">Recent Found Items</span>
                        </h5>
                        <a href="/found_items/index.php" class="btn btn-sm btn-neutral">View All</a>
                    </div>
                    <div class="card-body p-0">
                        ${!recentFound.length ? `
                        <div class="text-center py-5 text-muted">
                            <i class="fa-solid fa-inbox fs-2"></i>
                            <p class="small mt-2 mb-0">No found items registered yet.</p>
                        </div>` : `
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Item</th>
                                        <th>Category</th>
                                        <th>Storage</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${recentFound.map((item) => `
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-3">
                                                ${renderItemThumb(item, 'fa-solid fa-box-open')}
                                                <div>
                                                    <div class="fw-semibold text-dark">${esc(item.item_name)}</div>
                                                    <small class="font-meta text-muted">${esc(item.location_found)}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="category-badge">${esc(getCategoryName(item.category_id))}</span></td>
                                        <td class="small font-meta text-muted text-nowrap">
                                            <i class="fa-solid fa-location-dot me-1 text-secondary"></i>${esc(item.storage_location || 'Unassigned')}
                                        </td>
                                        <td><span class="badge status-badge-${esc(item.status)}">${esc(item.status.toUpperCase())}</span></td>
                                        <td class="text-end pe-4">
                                            <a href="/found_items/view.php?id=${item.id}" class="btn btn-view btn-action-icon" title="View Details">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>`).join('')}
                                </tbody>
                            </table>
                        </div>`}
                    </div>
                </div>
            </div>
        </div>
    </div>`;

  return renderLayout({ title: 'Dashboard', activeSection: 'dashboard', bodyHtml, extraCss: ['assets/css/dashboard.css'] });
}

function renderLostItemsIndex(query) {
  const search = (query.search || '').trim().toLowerCase();
  const catFilter = (query.category || '').trim();
  const statusFilter = (query.status || '').trim();
  const perPage = Math.max(1, parseInt(state.settings.items_per_page || '12', 10));

  const filtered = state.lostItems.filter((item) => {
    if (search) {
      const hay = `${item.item_name} ${item.description} ${item.location_lost} ${item.contact_info}`.toLowerCase();
      if (!hay.includes(search)) return false;
    }
    if (catFilter && String(item.category_id) !== catFilter) return false;
    if (statusFilter && item.status !== statusFilter) return false;
    return true;
  });

  const totalItems = filtered.length;
  const totalPages = Math.max(1, Math.ceil(totalItems / perPage));
  let page = Math.max(1, parseInt(query.page || '1', 10) || 1);
  if (page > totalPages) page = totalPages;
  const items = filtered.slice((page - 1) * perPage, page * perPage);

  const pageQuery = (p) => `/lost_items/index.php?page=${p}&search=${encodeURIComponent(query.search || '')}&category=${encodeURIComponent(catFilter)}&status=${encodeURIComponent(statusFilter)}`;

  const bodyHtml = `
    <div class="container-fluid">
        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
            <div>
                <h1 class="h3 fw-semibold mb-1">Lost Items Directory</h1>
                <p class="font-meta text-muted small mb-0">Browse and manage items reported lost across campus by students and faculty.</p>
            </div>
            <a href="/lost_items/report.php" class="btn btn-primary px-3 py-2">
                <i class="fa-solid fa-plus"></i>
                <span>Report Lost Item</span>
            </a>
        </div>

        ${renderFlash()}

        <div class="card mb-4">
            <div class="card-body p-3 p-md-4">
                <form method="GET" action="/lost_items/index.php" class="row g-2 g-md-3 align-items-center">
                    <div class="col-12 col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" name="search" class="form-control bg-light border-start-0" placeholder="Search item name, location, contact..." value="${esc(query.search || '')}">
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <select name="category" class="form-select bg-light">
                            <option value="">All Categories</option>
                            ${state.categories.map((c) => `<option value="${c.id}" ${catFilter === String(c.id) ? 'selected' : ''}>${esc(c.name)}</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <select name="status" class="form-select bg-light">
                            <option value="">All Statuses</option>
                            ${['open', 'matched', 'returned', 'closed'].map((s) => `<option value="${s}" ${statusFilter === s ? 'selected' : ''}>${s.charAt(0).toUpperCase() + s.slice(1)}</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <span>Filter</span>
                        </button>
                        <a href="/lost_items/index.php" class="btn btn-secondary" title="Reset Filters"><i class="fa-solid fa-rotate-left"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-body p-0">
                ${!items.length ? `
                <div class="text-center py-5">
                    <i class="fa-solid fa-magnifying-glass fs-1 text-muted"></i>
                    <h5 class="font-display fw-semibold text-dark mt-3">No lost items found</h5>
                    <p class="font-meta text-muted small mb-0">Try modifying your search criteria or report a new lost item.</p>
                </div>` : `
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Item Details</th>
                                <th>Category</th>
                                <th>Location Lost</th>
                                <th>Date Lost</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${items.map((item) => `
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        ${renderItemThumb(item, 'fa-regular fa-image')}
                                        <div>
                                            <a href="/lost_items/view.php?id=${item.id}" class="fw-semibold text-dark text-decoration-none">${esc(item.item_name)}</a>
                                            <p class="font-meta text-muted small text-truncate mb-0" style="max-width: 240px;">${esc(item.description)}</p>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="category-badge">${esc(getCategoryName(item.category_id))}</span></td>
                                <td class="small font-meta text-muted"><i class="fa-solid fa-location-dot me-1 text-secondary"></i>${esc(item.location_lost)}</td>
                                <td class="small font-meta text-muted text-nowrap">${fmtDate(item.date_lost)}</td>
                                <td class="small font-meta text-muted">${esc(item.contact_info)}</td>
                                <td><span class="badge status-badge-${esc(item.status)}">${esc(item.status.toUpperCase())}</span></td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <a href="/lost_items/view.php?id=${item.id}" class="btn btn-view btn-action-icon" title="View details"><i class="fa-solid fa-eye"></i></a>
                                        <a href="/lost_items/edit.php?id=${item.id}" class="btn btn-outline-primary btn-action-icon" title="Edit item"><i class="fa-solid fa-pen-to-square"></i></a>
                                        <form method="POST" action="/lost_items/process.php" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="${item.id}">
                                            <button type="submit" class="btn btn-danger btn-action-icon" title="Delete record"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>`).join('')}
                        </tbody>
                    </table>
                </div>
                ${totalPages > 1 ? `
                <div class="card-footer py-3 px-4 d-flex align-items-center justify-content-between">
                    <span class="small font-meta text-muted">Showing page ${page} of ${totalPages} (Total: ${totalItems} items)</span>
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item ${page <= 1 ? 'disabled' : ''}">
                                <a class="page-link" href="${pageQuery(page - 1)}">Previous</a>
                            </li>
                            ${Array.from({ length: totalPages }, (_, idx) => idx + 1).map((p) => `
                            <li class="page-item ${p === page ? 'active' : ''}">
                                <a class="page-link" href="${pageQuery(p)}">${p}</a>
                            </li>`).join('')}
                            <li class="page-item ${page >= totalPages ? 'disabled' : ''}">
                                <a class="page-link" href="${pageQuery(page + 1)}">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>` : ''}`}
            </div>
        </div>
    </div>`;

  return renderLayout({ title: 'Lost Items Directory', activeSection: 'lost_items', bodyHtml });
}

function renderReportLostPage() {
  const bodyHtml = `
    <div class="container-fluid" style="max-width: 900px;">
        <div class="mb-4">
            <a href="/lost_items/index.php" class="font-meta text-decoration-none text-muted small d-inline-flex align-items-center gap-2 mb-2">
                <i class="fa-solid fa-arrow-left"></i> Back to Lost Items
            </a>
            <h1 class="h3 fw-semibold mb-1">Report a Lost Item</h1>
            <p class="font-meta text-muted small mb-0">Fill in the details below to log a lost item in the FoundU registry.</p>
        </div>

        ${renderFlash()}

        <div class="card">
            <div class="card-body p-4 p-md-5">
                <form method="POST" action="/lost_items/process.php" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="create">
                    <div class="row g-3">
                        <div class="col-12 col-md-8">
                            <label for="itemName" class="form-label small">Item Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="itemName" name="item_name" placeholder="e.g., iPhone 15 Pro, Calculus Textbook, Hydro Flask" required>
                            <div class="invalid-feedback">Please provide the item name.</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="categoryId" class="form-label small">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="categoryId" name="category_id" required>
                                <option value="">Select Category...</option>
                                ${state.categories.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('')}
                            </select>
                            <div class="invalid-feedback">Please select a category.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="dateLost" class="form-label small">Date Lost <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="dateLost" name="date_lost" value="${todayStr()}" max="${todayStr()}" required>
                            <div class="invalid-feedback">Please select the approximate date the item was lost.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="locationLost" class="form-label small">Location Lost <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="locationLost" name="location_lost" placeholder="e.g., Engineering Lab B, 3rd Floor Library" required>
                            <div class="invalid-feedback">Please describe where the item was misplaced.</div>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label small">Description &amp; Identifying Details <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="4" placeholder="Mention brand, color, stickers, unique scratches, serial numbers, case type..." required></textarea>
                            <div class="invalid-feedback">Please provide identifying details.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="contactInfo" class="form-label small">Owner Contact Info <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="contactInfo" name="contact_info" placeholder="Email address or phone number" required>
                            <div class="form-text small">Used to notify the owner once a match is found.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="itemImage" class="form-label small">Photo of Item (Optional)</label>
                            <input type="file" class="form-control" id="itemImage" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            <div class="form-text small">Accepted formats: JPG, JPEG, PNG, WEBP (Max 5MB).</div>
                            <div class="image-upload-preview-card mt-2 d-none" data-image-preview-box></div>
                        </div>
                    </div>
                    <hr class="my-4">
                    <div class="d-flex justify-content-end gap-2">
                        <a href="/lost_items/index.php" class="btn btn-secondary px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">Submit Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>`;
  return renderLayout({ title: 'Report Lost Item', activeSection: 'lost_items', bodyHtml });
}

function renderLostItemView(id) {
  const item = state.lostItems.find((i) => i.id === Number(id));
  if (!item) return null;
  const existingMatch = state.matches.find((m) => m.lost_item_id === item.id);
  const matchedFound = existingMatch ? state.foundItems.find((f) => f.id === existingMatch.found_item_id) : null;
  const potentialMatches = state.foundItems.filter((f) => f.category_id === item.category_id && f.status === 'available').slice(0, 5);

  const bodyHtml = `
    <div class="container-fluid" style="max-width: 1050px;">
        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
            <div>
                <a href="/lost_items/index.php" class="font-meta text-decoration-none text-muted small d-inline-flex align-items-center gap-2 mb-2">
                    <i class="fa-solid fa-arrow-left"></i> Back to Lost Items
                </a>
                <h1 class="h3 fw-semibold mb-2">${esc(item.item_name)}</h1>
                <span class="badge status-badge-${esc(item.status)}">STATUS: ${esc(item.status.toUpperCase())}</span>
            </div>
            <div class="d-flex gap-2">
                <a href="/lost_items/edit.php?id=${item.id}" class="btn btn-outline-primary btn-sm">
                    <i class="fa-solid fa-pen-to-square"></i> Edit
                </a>
                <form method="POST" action="/lost_items/process.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${item.id}">
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </form>
            </div>
        </div>

        ${renderFlash()}

        ${existingMatch && matchedFound ? `
        <div class="alert alert-success p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon-wrapper stat-icon-primary" style="width: 46px; height: 46px;">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <div>
                        <h5 class="font-display fw-semibold mb-0">Matched with Found Item</h5>
                        <p class="font-meta small mb-0">
                            Linked with: <strong>${esc(matchedFound.item_name)}</strong> (Storage: ${esc(matchedFound.storage_location)})
                            &middot; Confirmed by ${esc(getUserName(existingMatch.confirmed_by))}
                        </p>
                    </div>
                </div>
                <a href="/matches/index.php" class="btn btn-neutral btn-sm px-3">View Match Details</a>
            </div>
        </div>` : ''}

        <div class="row g-4">
            <div class="col-12 col-lg-7">
                <div class="card mb-4">
                    <div class="card-body p-4">
                        <h5 class="font-display fw-semibold mb-3">Item Specifications</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <small class="font-meta text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Category</small>
                                <div class="fw-semibold text-dark mt-1">${esc(getCategoryName(item.category_id))}</div>
                            </div>
                            <div class="col-sm-6">
                                <small class="font-meta text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Date Lost</small>
                                <div class="fw-semibold text-dark mt-1"><i class="fa-solid fa-calendar-days me-1 text-primary"></i>${fmtDate(item.date_lost)}</div>
                            </div>
                            <div class="col-12">
                                <small class="font-meta text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Location Lost</small>
                                <div class="fw-semibold text-dark mt-1"><i class="fa-solid fa-location-dot me-1 text-secondary"></i>${esc(item.location_lost)}</div>
                            </div>
                        </div>
                        <h6 class="fw-semibold text-dark mb-2">Description</h6>
                        <div class="p-3 bg-light rounded-3 text-secondary mb-4 border">${esc(item.description)}</div>
                        <h6 class="fw-semibold text-dark mb-2">Owner Contact Information</h6>
                        <div class="p-3 bg-light rounded-3 d-flex align-items-center gap-2 text-dark border">
                            <i class="fa-solid fa-phone text-primary"></i>
                            <span class="font-meta fw-semibold">${esc(item.contact_info)}</span>
                        </div>
                    </div>
                </div>

                ${item.status === 'open' ? `
                <div class="card overflow-hidden">
                    <div class="card-header py-3 px-4">
                        <h5 class="font-display fw-semibold mb-0 d-flex align-items-center gap-2 fs-6">
                            <i class="fa-solid fa-link text-primary"></i> Potential Found Matches in Storage
                        </h5>
                        <small class="font-meta text-muted">Available items registered in category: ${esc(getCategoryName(item.category_id))}</small>
                    </div>
                    <div class="card-body p-0">
                        ${!potentialMatches.length ? `<div class="p-4 text-center font-meta text-muted small">No matching items currently available in storage.</div>` : `
                        <div class="list-group list-group-flush">
                            ${potentialMatches.map((found) => `
                            <div class="list-group-item p-3 d-flex align-items-center justify-content-between gap-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="stat-icon-wrapper stat-icon-success" style="width: 42px; height: 42px; font-size: 1rem;">
                                        <i class="fa-solid fa-box-open"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-semibold text-dark mb-0">${esc(found.item_name)}</h6>
                                        <small class="font-meta text-muted">Found on ${fmtDate(found.date_found)} &middot; Storage: ${esc(found.storage_location)}</small>
                                    </div>
                                </div>
                                <form method="POST" action="/matches/process.php">
                                    <input type="hidden" name="action" value="create">
                                    <input type="hidden" name="lost_item_id" value="${item.id}">
                                    <input type="hidden" name="found_item_id" value="${found.id}">
                                    <input type="hidden" name="notes" value="Matched automatically via item view recommendation.">
                                    <button type="submit" class="btn btn-sm btn-success text-nowrap">
                                        <i class="fa-solid fa-link"></i> Link &amp; Match
                                    </button>
                                </form>
                            </div>`).join('')}
                        </div>`}
                    </div>
                </div>` : ''}
            </div>

            <div class="col-12 col-lg-5">
                <div class="card mb-4 overflow-hidden">
                    ${renderItemDetailMedia(item, 'fa-regular fa-image', 'No image uploaded for this item')}
                </div>
                <div class="card">
                    <div class="card-body p-4">
                        <h6 class="font-display fw-semibold text-dark mb-3">Audit Details</h6>
                        <ul class="list-unstyled mb-0 small font-meta text-muted">
                            <li class="d-flex justify-content-between py-2 border-bottom">
                                <span>Record ID</span>
                                <span class="fw-bold text-dark">#${item.id}</span>
                            </li>
                            <li class="d-flex justify-content-between py-2 border-bottom">
                                <span>Reported By</span>
                                <span class="fw-semibold text-dark">${esc(getUserName(item.reported_by))}</span>
                            </li>
                            <li class="d-flex justify-content-between py-2 border-bottom">
                                <span>Created At</span>
                                <span>${fmtDateTime(item.created_at)}</span>
                            </li>
                            <li class="d-flex justify-content-between py-2">
                                <span>Last Updated</span>
                                <span>${fmtDateTime(item.updated_at || item.created_at)}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>`;

  return renderLayout({ title: `View Lost Item: ${item.item_name}`, activeSection: 'lost_items', bodyHtml });
}

function renderLostItemEdit(id) {
  const item = state.lostItems.find((i) => i.id === Number(id));
  if (!item) return null;

  const bodyHtml = `
    <div class="container-fluid" style="max-width: 900px;">
        <div class="mb-4">
            <a href="/lost_items/view.php?id=${item.id}" class="font-meta text-decoration-none text-muted small d-inline-flex align-items-center gap-2 mb-2">
                <i class="fa-solid fa-arrow-left"></i> Back to Item Details
            </a>
            <h1 class="h3 fw-semibold mb-1">Edit Lost Item #${item.id}</h1>
            <p class="font-meta text-muted small mb-0">Update item details, location, or change resolution status.</p>
        </div>

        ${renderFlash()}

        <div class="card">
            <div class="card-body p-4 p-md-5">
                <form method="POST" action="/lost_items/process.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="${item.id}">
                    <div class="row g-3">
                        <div class="col-12 col-md-8">
                            <label for="itemName" class="form-label small">Item Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="itemName" name="item_name" value="${esc(item.item_name)}" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="status" class="form-label small">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                ${['open', 'matched', 'returned', 'closed'].map((s) => `<option value="${s}" ${item.status === s ? 'selected' : ''}>${s.charAt(0).toUpperCase() + s.slice(1)}</option>`).join('')}
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="categoryId" class="form-label small">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="categoryId" name="category_id" required>
                                ${state.categories.map((c) => `<option value="${c.id}" ${item.category_id === c.id ? 'selected' : ''}>${esc(c.name)}</option>`).join('')}
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="dateLost" class="form-label small">Date Lost <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="dateLost" name="date_lost" value="${esc(item.date_lost)}" required>
                        </div>
                        <div class="col-12">
                            <label for="locationLost" class="form-label small">Location Lost <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="locationLost" name="location_lost" value="${esc(item.location_lost)}" required>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label small">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="4" required>${esc(item.description)}</textarea>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="contactInfo" class="form-label small">Contact Info <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="contactInfo" name="contact_info" value="${esc(item.contact_info)}" required>
                        </div>
                        ${renderEditImageSection(item, 'col-12 col-md-6')}
                    </div>
                    <hr class="my-4">
                    <div class="d-flex justify-content-end gap-2">
                        <a href="/lost_items/view.php?id=${item.id}" class="btn btn-secondary px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>`;
  return renderLayout({ title: `Edit Lost Item: ${item.item_name}`, activeSection: 'lost_items', bodyHtml });
}

function renderFoundItemsIndex(query) {
  const search = (query.search || '').trim().toLowerCase();
  const catFilter = (query.category || '').trim();
  const statusFilter = (query.status || '').trim();
  const perPage = Math.max(1, parseInt(state.settings.items_per_page || '12', 10));

  const filtered = state.foundItems.filter((item) => {
    if (search) {
      const hay = `${item.item_name} ${item.description} ${item.location_found} ${item.storage_location}`.toLowerCase();
      if (!hay.includes(search)) return false;
    }
    if (catFilter && String(item.category_id) !== catFilter) return false;
    if (statusFilter && item.status !== statusFilter) return false;
    return true;
  });

  const totalItems = filtered.length;
  const totalPages = Math.max(1, Math.ceil(totalItems / perPage));
  let page = Math.max(1, parseInt(query.page || '1', 10) || 1);
  if (page > totalPages) page = totalPages;
  const items = filtered.slice((page - 1) * perPage, page * perPage);

  const pageQuery = (p) => `/found_items/index.php?page=${p}&search=${encodeURIComponent(query.search || '')}&category=${encodeURIComponent(catFilter)}&status=${encodeURIComponent(statusFilter)}`;

  const bodyHtml = `
    <div class="container-fluid">
        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
            <div>
                <h1 class="h3 fw-semibold mb-1">Found Items Inventory</h1>
                <p class="font-meta text-muted small mb-0">Custody records of recovered belongings kept in campus security lockers.</p>
            </div>
            <a href="/found_items/add.php" class="btn btn-primary px-3 py-2">
                <i class="fa-solid fa-plus"></i>
                <span>Record Found Item</span>
            </a>
        </div>

        ${renderFlash()}

        <div class="card mb-4">
            <div class="card-body p-3 p-md-4">
                <form method="GET" action="/found_items/index.php" class="row g-2 g-md-3 align-items-center">
                    <div class="col-12 col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" name="search" class="form-control bg-light border-start-0" placeholder="Search item, location found, storage locker..." value="${esc(query.search || '')}">
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <select name="category" class="form-select bg-light">
                            <option value="">All Categories</option>
                            ${state.categories.map((c) => `<option value="${c.id}" ${catFilter === String(c.id) ? 'selected' : ''}>${esc(c.name)}</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <select name="status" class="form-select bg-light">
                            <option value="">All Statuses</option>
                            ${['available', 'matched', 'returned', 'closed'].map((s) => `<option value="${s}" ${statusFilter === s ? 'selected' : ''}>${s.charAt(0).toUpperCase() + s.slice(1)}</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <span>Filter</span>
                        </button>
                        <a href="/found_items/index.php" class="btn btn-secondary" title="Reset Filters"><i class="fa-solid fa-rotate-left"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-body p-0">
                ${!items.length ? `
                <div class="text-center py-5">
                    <i class="fa-solid fa-inbox fs-1 text-muted"></i>
                    <h5 class="font-display fw-semibold text-dark mt-3">No found items in storage</h5>
                    <p class="font-meta text-muted small mb-0">Record an item handed in by security or campus staff.</p>
                </div>` : `
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Item Details</th>
                                <th>Category</th>
                                <th>Location Found</th>
                                <th>Storage Location</th>
                                <th>Date Found</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${items.map((item) => `
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        ${renderItemThumb(item, 'fa-solid fa-box-open')}
                                        <div>
                                            <a href="/found_items/view.php?id=${item.id}" class="fw-semibold text-dark text-decoration-none">${esc(item.item_name)}</a>
                                            <p class="font-meta text-muted small text-truncate mb-0" style="max-width: 240px;">${esc(item.description)}</p>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="category-badge">${esc(getCategoryName(item.category_id))}</span></td>
                                <td class="small font-meta text-muted"><i class="fa-solid fa-location-dot me-1 text-secondary"></i>${esc(item.location_found)}</td>
                                <td>
                                    <span class="storage-badge">
                                        <i class="fa-solid fa-box-archive"></i>
                                        <span>${esc(item.storage_location || 'Unassigned')}</span>
                                    </span>
                                </td>
                                <td class="small font-meta text-muted text-nowrap">${fmtDate(item.date_found)}</td>
                                <td><span class="badge status-badge-${esc(item.status)}">${esc(item.status.toUpperCase())}</span></td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <a href="/found_items/view.php?id=${item.id}" class="btn btn-view btn-action-icon" title="View details"><i class="fa-solid fa-eye"></i></a>
                                        <a href="/found_items/edit.php?id=${item.id}" class="btn btn-outline-primary btn-action-icon" title="Edit record"><i class="fa-solid fa-pen-to-square"></i></a>
                                        <form method="POST" action="/found_items/process.php" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="${item.id}">
                                            <button type="submit" class="btn btn-danger btn-action-icon" title="Delete record"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>`).join('')}
                        </tbody>
                    </table>
                </div>
                ${totalPages > 1 ? `
                <div class="card-footer py-3 px-4 d-flex align-items-center justify-content-between">
                    <span class="small font-meta text-muted">Showing page ${page} of ${totalPages} (Total: ${totalItems} items)</span>
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item ${page <= 1 ? 'disabled' : ''}">
                                <a class="page-link" href="${pageQuery(page - 1)}">Previous</a>
                            </li>
                            ${Array.from({ length: totalPages }, (_, idx) => idx + 1).map((p) => `
                            <li class="page-item ${p === page ? 'active' : ''}">
                                <a class="page-link" href="${pageQuery(p)}">${p}</a>
                            </li>`).join('')}
                            <li class="page-item ${page >= totalPages ? 'disabled' : ''}">
                                <a class="page-link" href="${pageQuery(page + 1)}">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>` : ''}`}
            </div>
        </div>
    </div>`;

  return renderLayout({ title: 'Found Items Inventory', activeSection: 'found_items', bodyHtml });
}

function renderFoundAddPage() {
  const bodyHtml = `
    <div class="container-fluid" style="max-width: 900px;">
        <div class="mb-4">
            <a href="/found_items/index.php" class="font-meta text-decoration-none text-muted small d-inline-flex align-items-center gap-2 mb-2">
                <i class="fa-solid fa-arrow-left"></i> Back to Found Inventory
            </a>
            <h1 class="h3 fw-semibold mb-1">Record Found Item</h1>
            <p class="font-meta text-muted small mb-0">Catalog a recovered item turned into campus security or lost &amp; found office.</p>
        </div>

        ${renderFlash()}

        <div class="card">
            <div class="card-body p-4 p-md-5">
                <form method="POST" action="/found_items/process.php" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="create">
                    <div class="row g-3">
                        <div class="col-12 col-md-8">
                            <label for="itemName" class="form-label small">Item Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="itemName" name="item_name" placeholder="e.g., Wireless Earbuds, Silver Watch, Car Keys" required>
                            <div class="invalid-feedback">Please enter the item name.</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="categoryId" class="form-label small">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="categoryId" name="category_id" required>
                                <option value="">Select Category...</option>
                                ${state.categories.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('')}
                            </select>
                            <div class="invalid-feedback">Please select a category.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="dateFound" class="form-label small">Date Found <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="dateFound" name="date_found" value="${todayStr()}" max="${todayStr()}" required>
                            <div class="invalid-feedback">Please select the date found.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="locationFound" class="form-label small">Location Found <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="locationFound" name="location_found" placeholder="e.g., Cafeteria Booth #4, Gym Bleachers" required>
                            <div class="invalid-feedback">Please specify where it was picked up.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="storageLocation" class="form-label small">Storage Location / Locker ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="storageLocation" name="storage_location" placeholder="e.g., Locker #14, Admin Safe Bin B" required>
                            <div class="form-text small">Physical location where the item is stored in campus custody.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="itemImage" class="form-label small">Photo of Item (Optional)</label>
                            <input type="file" class="form-control" id="itemImage" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            <div class="form-text small">Accepted formats: JPG, JPEG, PNG, WEBP (Max 5MB).</div>
                            <div class="image-upload-preview-card mt-2 d-none" data-image-preview-box></div>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label small">Item Description &amp; Condition <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="3" placeholder="Condition, color, brand, distinct marks, stickers..." required></textarea>
                            <div class="invalid-feedback">Please describe the found item.</div>
                        </div>
                        <div class="col-12">
                            <label for="notes" class="form-label small">Internal Custody Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Turned in by student assistant, handed in with bag, etc."></textarea>
                        </div>
                    </div>
                    <hr class="my-4">
                    <div class="d-flex justify-content-end gap-2">
                        <a href="/found_items/index.php" class="btn btn-secondary px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">Save Found Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>`;
  return renderLayout({ title: 'Record Found Item', activeSection: 'found_items', bodyHtml });
}

function renderFoundItemView(id) {
  const item = state.foundItems.find((i) => i.id === Number(id));
  if (!item) return null;
  const existingMatch = state.matches.find((m) => m.found_item_id === item.id);
  const matchedLost = existingMatch ? state.lostItems.find((l) => l.id === existingMatch.lost_item_id) : null;
  const openLostItems = state.lostItems.filter((l) => l.category_id === item.category_id && l.status === 'open').slice(0, 5);

  const bodyHtml = `
    <div class="container-fluid" style="max-width: 1050px;">
        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
            <div>
                <a href="/found_items/index.php" class="font-meta text-decoration-none text-muted small d-inline-flex align-items-center gap-2 mb-2">
                    <i class="fa-solid fa-arrow-left"></i> Back to Found Inventory
                </a>
                <h1 class="h3 fw-semibold mb-2">${esc(item.item_name)}</h1>
                <span class="badge status-badge-${esc(item.status)}">STATUS: ${esc(item.status.toUpperCase())}</span>
            </div>
            <div class="d-flex gap-2">
                <a href="/found_items/edit.php?id=${item.id}" class="btn btn-outline-primary btn-sm">
                    <i class="fa-solid fa-pen-to-square"></i> Edit
                </a>
                <form method="POST" action="/found_items/process.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${item.id}">
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </form>
            </div>
        </div>

        ${renderFlash()}

        ${existingMatch && matchedLost ? `
        <div class="alert alert-success p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon-wrapper stat-icon-primary" style="width: 46px; height: 46px;">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <div>
                        <h5 class="font-display fw-semibold mb-0">Matched with Lost Item</h5>
                        <p class="font-meta small mb-0">
                            Linked with lost report: <strong>${esc(matchedLost.item_name)}</strong>
                            (Owner contact: ${esc(matchedLost.contact_info)})
                            &middot; Confirmed by ${esc(getUserName(existingMatch.confirmed_by))}
                        </p>
                    </div>
                </div>
                <a href="/matches/index.php" class="btn btn-neutral btn-sm px-3">View Match Details</a>
            </div>
        </div>` : ''}

        <div class="row g-4">
            <div class="col-12 col-lg-7">
                <div class="card mb-4">
                    <div class="card-body p-4">
                        <h5 class="font-display fw-semibold mb-3">Item Specifications</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <small class="font-meta text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Category</small>
                                <div class="fw-semibold text-dark mt-1">${esc(getCategoryName(item.category_id))}</div>
                            </div>
                            <div class="col-sm-6">
                                <small class="font-meta text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Date Found</small>
                                <div class="fw-semibold text-dark mt-1"><i class="fa-solid fa-calendar-days me-1 text-primary"></i>${fmtDate(item.date_found)}</div>
                            </div>
                            <div class="col-sm-6">
                                <small class="font-meta text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Location Found</small>
                                <div class="fw-semibold text-dark mt-1"><i class="fa-solid fa-location-dot me-1 text-secondary"></i>${esc(item.location_found)}</div>
                            </div>
                            <div class="col-sm-6">
                                <small class="font-meta text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Storage Locker / Custody</small>
                                <div class="fw-semibold text-primary mt-1"><i class="fa-solid fa-box-archive me-1"></i>${esc(item.storage_location)}</div>
                            </div>
                        </div>
                        <h6 class="fw-semibold text-dark mb-2">Description</h6>
                        <div class="p-3 bg-light rounded-3 text-secondary mb-4 border">${esc(item.description)}</div>
                        ${item.notes ? `
                        <h6 class="fw-semibold text-dark mb-2">Internal Staff Custody Notes</h6>
                        <div class="p-3 bg-light rounded-3 text-secondary small border">
                            <i class="fa-solid fa-note-sticky me-1 text-primary"></i> ${esc(item.notes)}
                        </div>` : ''}
                    </div>
                </div>

                ${item.status === 'available' ? `
                <div class="card overflow-hidden">
                    <div class="card-header py-3 px-4">
                        <h5 class="font-display fw-semibold mb-0 d-flex align-items-center gap-2 fs-6">
                            <i class="fa-solid fa-link text-primary"></i> Potential Open Lost Reports
                        </h5>
                        <small class="font-meta text-muted">Open reports registered under category: ${esc(getCategoryName(item.category_id))}</small>
                    </div>
                    <div class="card-body p-0">
                        ${!openLostItems.length ? `<div class="p-4 text-center font-meta text-muted small">No open lost reports currently match this item's category.</div>` : `
                        <div class="list-group list-group-flush">
                            ${openLostItems.map((lost) => `
                            <div class="list-group-item p-3 d-flex align-items-center justify-content-between gap-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="stat-icon-wrapper stat-icon-warning" style="width: 42px; height: 42px; font-size: 1rem;">
                                        <i class="fa-solid fa-circle-question"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-semibold text-dark mb-0">${esc(lost.item_name)}</h6>
                                        <small class="font-meta text-muted">Lost on ${fmtDate(lost.date_lost)} at ${esc(lost.location_lost)}</small>
                                    </div>
                                </div>
                                <form method="POST" action="/matches/process.php">
                                    <input type="hidden" name="action" value="create">
                                    <input type="hidden" name="lost_item_id" value="${lost.id}">
                                    <input type="hidden" name="found_item_id" value="${item.id}">
                                    <input type="hidden" name="notes" value="Matched via found item inspection.">
                                    <button type="submit" class="btn btn-sm btn-success text-nowrap">
                                        <i class="fa-solid fa-link"></i> Link &amp; Match
                                    </button>
                                </form>
                            </div>`).join('')}
                        </div>`}
                    </div>
                </div>` : ''}
            </div>

            <div class="col-12 col-lg-5">
                <div class="card mb-4 overflow-hidden">
                    ${renderItemDetailMedia(item, 'fa-solid fa-box-open', 'No photo uploaded for this item')}
                </div>
                <div class="card">
                    <div class="card-body p-4">
                        <h6 class="font-display fw-semibold text-dark mb-3">Custody Information</h6>
                        <ul class="list-unstyled mb-0 small font-meta text-muted">
                            <li class="d-flex justify-content-between py-2 border-bottom">
                                <span>Inventory ID</span>
                                <span class="fw-bold text-dark">#${item.id}</span>
                            </li>
                            <li class="d-flex justify-content-between py-2 border-bottom">
                                <span>Recorded By</span>
                                <span class="fw-semibold text-dark">${esc(getUserName(item.recorded_by))}</span>
                            </li>
                            <li class="d-flex justify-content-between py-2 border-bottom">
                                <span>Recorded On</span>
                                <span>${fmtDateTime(item.created_at)}</span>
                            </li>
                            <li class="d-flex justify-content-between align-items-center py-2">
                                <span>Storage Location</span>
                                <span class="storage-badge">${esc(item.storage_location)}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>`;

  return renderLayout({ title: `View Found Item: ${item.item_name}`, activeSection: 'found_items', bodyHtml });
}

function renderFoundItemEdit(id) {
  const item = state.foundItems.find((i) => i.id === Number(id));
  if (!item) return null;

  const bodyHtml = `
    <div class="container-fluid" style="max-width: 900px;">
        <div class="mb-4">
            <a href="/found_items/view.php?id=${item.id}" class="font-meta text-decoration-none text-muted small d-inline-flex align-items-center gap-2 mb-2">
                <i class="fa-solid fa-arrow-left"></i> Back to Found Item Details
            </a>
            <h1 class="h3 fw-semibold mb-1">Edit Found Item #${item.id}</h1>
            <p class="font-meta text-muted small mb-0">Update item description, storage locker assignment, or custody status.</p>
        </div>

        ${renderFlash()}

        <div class="card">
            <div class="card-body p-4 p-md-5">
                <form method="POST" action="/found_items/process.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="${item.id}">
                    <div class="row g-3">
                        <div class="col-12 col-md-8">
                            <label for="itemName" class="form-label small">Item Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="itemName" name="item_name" value="${esc(item.item_name)}" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="status" class="form-label small">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="available" ${item.status === 'available' ? 'selected' : ''}>Available (In Storage)</option>
                                <option value="matched" ${item.status === 'matched' ? 'selected' : ''}>Matched (Linked)</option>
                                <option value="returned" ${item.status === 'returned' ? 'selected' : ''}>Returned (Claimed)</option>
                                <option value="closed" ${item.status === 'closed' ? 'selected' : ''}>Closed</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="categoryId" class="form-label small">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="categoryId" name="category_id" required>
                                ${state.categories.map((c) => `<option value="${c.id}" ${item.category_id === c.id ? 'selected' : ''}>${esc(c.name)}</option>`).join('')}
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="dateFound" class="form-label small">Date Found <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="dateFound" name="date_found" value="${esc(item.date_found)}" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="locationFound" class="form-label small">Location Found <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="locationFound" name="location_found" value="${esc(item.location_found)}" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="storageLocation" class="form-label small">Storage Location / Locker <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="storageLocation" name="storage_location" value="${esc(item.storage_location)}" required>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label small">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="3" required>${esc(item.description)}</textarea>
                        </div>
                        <div class="col-12">
                            <label for="notes" class="form-label small">Internal Custody Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2">${esc(item.notes)}</textarea>
                        </div>
                        ${renderEditImageSection(item, 'col-12')}
                    </div>
                    <hr class="my-4">
                    <div class="d-flex justify-content-end gap-2">
                        <a href="/found_items/view.php?id=${item.id}" class="btn btn-secondary px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>`;
  return renderLayout({ title: `Edit Found Item: ${item.item_name}`, activeSection: 'found_items', bodyHtml });
}

function renderMatchesPage() {
  const openLostItems = state.lostItems.filter((i) => i.status === 'open');
  const availFoundItems = state.foundItems.filter((i) => i.status === 'available');

  const bodyHtml = `
    <div class="container-fluid">
        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
            <div>
                <h1 class="h3 fw-semibold mb-1">Item Matches &amp; Claims</h1>
                <p class="font-meta text-muted small mb-0">Link reported lost items with recovered items in storage to coordinate owner verification and return.</p>
            </div>
            <button type="button" class="btn btn-primary px-3 py-2" data-bs-toggle="modal" data-bs-target="#newMatchModal">
                <i class="fa-solid fa-link"></i>
                <span>Create New Match</span>
            </button>
        </div>

        ${renderFlash()}

        <div class="card overflow-hidden">
            <div class="card-body p-0">
                ${!state.matches.length ? `
                <div class="text-center py-5">
                    <i class="fa-solid fa-link fs-1 text-muted"></i>
                    <h5 class="font-display fw-semibold text-dark mt-3">No matches created yet</h5>
                    <p class="font-meta text-muted small">Link an open lost report with an item currently kept in storage.</p>
                    <button class="btn btn-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#newMatchModal">
                        <i class="fa-solid fa-plus"></i> Create First Match
                    </button>
                </div>` : `
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Match ID</th>
                                <th>Lost Item Report</th>
                                <th>Found Custody Item</th>
                                <th>Confirmed By</th>
                                <th>Verification Notes</th>
                                <th>Date Linked</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${state.matches.map((m) => {
                              const lost = state.lostItems.find((l) => l.id === m.lost_item_id) || {};
                              const found = state.foundItems.find((f) => f.id === m.found_item_id) || {};
                              return `
                            <tr>
                                <td class="ps-4 font-meta fw-bold text-primary">#${m.id}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        ${renderItemThumb(lost, 'fa-regular fa-image')}
                                        <div>
                                            <a href="/lost_items/view.php?id=${m.lost_item_id}" class="fw-semibold text-dark text-decoration-none d-block">${esc(lost.item_name)}</a>
                                            <small class="font-meta text-muted">Contact: ${esc(lost.contact_info)}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        ${renderItemThumb(found, 'fa-solid fa-box-open')}
                                        <div>
                                            <a href="/found_items/view.php?id=${m.found_item_id}" class="fw-semibold text-dark text-decoration-none d-block mb-1">${esc(found.item_name)}</a>
                                            <span class="storage-badge">
                                                <i class="fa-solid fa-box-archive"></i>
                                                <span>${esc(found.storage_location)}</span>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="small fw-medium text-secondary">${esc(getUserName(m.confirmed_by))}</td>
                                <td class="small font-meta text-muted" style="max-width: 200px;">${esc(m.notes || '—')}</td>
                                <td class="small font-meta text-muted text-nowrap">${fmtDateTime(m.created_at)}</td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        ${lost.status !== 'returned' ? `
                                        <form method="POST" action="/matches/process.php" class="d-inline">
                                            <input type="hidden" name="action" value="mark_returned">
                                            <input type="hidden" name="match_id" value="${m.id}">
                                            <button type="submit" class="btn btn-success btn-sm" title="Mark as Claimed &amp; Returned to Owner">
                                                <i class="fa-solid fa-check"></i>
                                                <span>Return</span>
                                            </button>
                                        </form>` : `<span class="badge status-badge-claimed">RETURNED</span>`}
                                        <form method="POST" action="/matches/process.php" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="match_id" value="${m.id}">
                                            <button type="submit" class="btn btn-danger btn-action-icon" title="Unlink match">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>`;
                            }).join('')}
                        </tbody>
                    </table>
                </div>`}
            </div>
        </div>
    </div>

    <div class="modal fade" id="newMatchModal" tabindex="-1" aria-labelledby="newMatchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title font-display fw-semibold text-dark" id="newMatchModalLabel">Create Item Match</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="/matches/process.php">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-body py-4">
                        <div class="mb-3">
                            <label for="lostItemId" class="form-label small">Select Lost Item Report <span class="text-danger">*</span></label>
                            <select class="form-select" id="lostItemId" name="lost_item_id" required>
                                <option value="">Choose open lost item...</option>
                                ${openLostItems.map((li) => `<option value="${li.id}">#${li.id} - ${esc(li.item_name)} (Lost: ${fmtDate(li.date_lost)})</option>`).join('')}
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="foundItemId" class="form-label small">Select Found Item in Custody <span class="text-danger">*</span></label>
                            <select class="form-select" id="foundItemId" name="found_item_id" required>
                                <option value="">Choose available found item...</option>
                                ${availFoundItems.map((fi) => `<option value="${fi.id}">#${fi.id} - ${esc(fi.item_name)} (Locker: ${esc(fi.storage_location)})</option>`).join('')}
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="matchNotes" class="form-label small">Verification &amp; Handover Notes</label>
                            <textarea class="form-control" id="matchNotes" name="notes" rows="3" placeholder="Student ID verified, serial number matched, pickup date scheduled..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4">Confirm Match</button>
                    </div>
                </form>
            </div>
        </div>
    </div>`;
  return renderLayout({ title: 'Matches & Claims', activeSection: 'matches', bodyHtml });
}

function renderManageUsersPage() {
  const cu = currentUser();
  const bodyHtml = `
    <div class="container-fluid">
        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
            <div>
                <h1 class="h3 fw-semibold mb-1">User &amp; Operator Management</h1>
                <p class="font-meta text-muted small mb-0">Manage staff and administrative accounts authorized to access the FoundU system.</p>
            </div>
            <button type="button" class="btn btn-primary px-3 py-2" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fa-solid fa-plus"></i>
                <span>Add New Operator</span>
            </button>
        </div>

        ${renderFlash()}

        <div class="card overflow-hidden">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">User</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Created</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${state.users.map((u) => `
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="user-avatar-circle" style="width: 38px; height: 38px; font-size: 0.95rem;">
                                            ${esc(u.full_name.charAt(0).toUpperCase())}
                                        </div>
                                        <div class="fw-semibold text-dark">${esc(u.full_name)}</div>
                                    </div>
                                </td>
                                <td class="font-meta text-muted">@${esc(u.username)}</td>
                                <td class="small font-meta text-muted">${esc(u.email || '—')}</td>
                                <td>
                                    <span class="badge role-badge-${u.role === 'admin' ? 'admin' : 'staff'}">
                                        ${esc(u.role.toUpperCase())}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge status-badge-${u.status === 'active' ? 'active' : 'inactive'}">
                                        ${esc(u.status.toUpperCase())}
                                    </span>
                                </td>
                                <td class="small font-meta text-muted text-nowrap">${fmtDateTime(u.last_login)}</td>
                                <td class="small font-meta text-muted text-nowrap">${fmtDate(u.created_at)}</td>
                                <td class="text-end pe-4">
                                    ${u.id !== cu.id ? `
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <form method="POST" action="/admin/manage_users.php" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="${u.id}">
                                            <input type="hidden" name="status" value="${u.status === 'active' ? 'inactive' : 'active'}">
                                            <button type="submit" class="btn ${u.status === 'active' ? 'btn-warning' : 'btn-success'} btn-action-icon" title="${u.status === 'active' ? 'Deactivate Account' : 'Activate Account'}">
                                                <i class="fa-solid fa-power-off"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="/admin/manage_users.php" class="d-inline">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="${u.id}">
                                            <button type="submit" class="btn btn-danger btn-action-icon" title="Delete User">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>` : `<span class="font-meta small text-muted">Current User</span>`}
                                </td>
                            </tr>`).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title font-display fw-semibold text-dark" id="addUserModalLabel">Add New Operator</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="/admin/manage_users.php">
                    <input type="hidden" name="action" value="create_user">
                    <div class="modal-body py-4">
                        <div class="mb-3">
                            <label for="newFullName" class="form-label small">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="newFullName" name="full_name" placeholder="John Doe" required>
                        </div>
                        <div class="mb-3">
                            <label for="newUsername" class="form-label small">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="newUsername" name="username" placeholder="johndoe" required>
                        </div>
                        <div class="mb-3">
                            <label for="newEmail" class="form-label small">Email Address</label>
                            <input type="email" class="form-control" id="newEmail" name="email" placeholder="john@foundu.edu">
                        </div>
                        <div class="mb-3">
                            <label for="newPassword" class="form-label small">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="newPassword" name="password" placeholder="At least 6 characters" minlength="6" required>
                        </div>
                        <div class="mb-3">
                            <label for="newRole" class="form-label small">Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="newRole" name="role" required>
                                <option value="staff" selected>Staff Operator (Log items, manage claims)</option>
                                <option value="admin">Administrator (Full system &amp; user access)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4">Create Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>`;
  return renderLayout({ title: 'Manage Users', activeSection: 'manage_users', bodyHtml });
}

function buildActivityLogData(query) {
  const rawSearch = (query.search || '').trim();
  const search = rawSearch.toLowerCase();
  const perPage = 25;

  const filtered = state.activityLogs.filter((l) => {
    if (!search) return true;
    const u = state.users.find((x) => x.id === l.user_id);
    const uname = u ? u.username : '';
    const fullName = u ? u.full_name : '';
    return `${l.action} ${l.details} ${uname} ${fullName} ${l.ip_address}`.toLowerCase().includes(search);
  });

  const totalLogs = filtered.length;
  const totalPages = Math.max(1, Math.ceil(totalLogs / perPage));
  let page = Math.max(1, parseInt(query.page || '1', 10) || 1);
  if (page > totalPages) page = totalPages;
  const logs = filtered.slice((page - 1) * perPage, page * perPage);

  const pageUrl = (p) => `/admin/activity_log.php?page=${p}&search=${encodeURIComponent(rawSearch)}`;

  const resultsHtml = `
    <div class="card-body p-0">
        ${!logs.length ? `
        <div class="text-center py-5 text-muted px-3">
            <i class="fa-solid fa-clock-rotate-left fs-1"></i>
            <h6 class="font-display fw-semibold text-dark mt-3 mb-1">
                ${rawSearch ? 'No matching activity logs found' : 'No activity logs recorded yet'}
            </h6>
            <p class="font-meta small mb-0">
                ${rawSearch
                  ? `No records matched "${esc(rawSearch)}". Try another keyword or clear the search input.`
                  : 'System logins, item changes, and administrative actions will appear here.'}
            </p>
        </div>` : `
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Operator</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>Details</th>
                        <th>IP Address</th>
                        <th class="pe-4 text-end">Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    ${logs.map((l) => {
                      const u = state.users.find((x) => x.id === l.user_id);
                      return `
                    <tr>
                        <td class="ps-4 font-meta text-muted small">#${l.id}</td>
                        <td>
                            ${u ? `<span class="fw-semibold text-dark">${esc(u.full_name)}</span><small class="font-meta text-muted d-block">@${esc(u.username)}</small>` : `<span class="category-badge">System / Guest</span>`}
                        </td>
                        <td><span class="badge role-badge-staff font-meta">${esc(l.action)}</span></td>
                        <td class="small font-meta text-muted">${esc(l.target_type || '—')} ${l.target_id ? '#' + l.target_id : ''}</td>
                        <td class="small text-dark text-break" style="max-width: 320px;">${esc(l.details)}</td>
                        <td class="small font-meta text-muted">${esc(l.ip_address)}</td>
                        <td class="pe-4 text-end small font-meta text-muted text-nowrap">${fmtDateTime(l.created_at)}</td>
                    </tr>`;
                    }).join('')}
                </tbody>
            </table>
        </div>
        ${totalPages > 1 ? `
        <div class="card-footer py-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span class="small font-meta text-muted">Showing page ${page} of ${totalPages} (Total: ${totalLogs} records)</span>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item ${page <= 1 ? 'disabled' : ''}">
                        <a class="page-link" href="${pageUrl(page - 1)}">Previous</a>
                    </li>
                    ${Array.from({ length: totalPages }, (_, idx) => idx + 1).map((p) => `
                    <li class="page-item ${p === page ? 'active' : ''}">
                        <a class="page-link" href="${pageUrl(p)}">${p}</a>
                    </li>`).join('')}
                    <li class="page-item ${page >= totalPages ? 'disabled' : ''}">
                        <a class="page-link" href="${pageUrl(page + 1)}">Next</a>
                    </li>
                </ul>
            </nav>
        </div>` : ''}`}
    </div>`;

  return { search: rawSearch, page, totalPages, totalLogs, html: resultsHtml };
}

function renderActivityLogPage(query) {
  const data = buildActivityLogData(query);

  const bodyHtml = `
    <div class="container-fluid">
        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
            <div>
                <h1 class="h3 fw-semibold mb-1">System Activity Logs</h1>
                <p class="font-meta text-muted small mb-0">Immutable audit trail of operator logins, item registries, status updates, and administrative actions.</p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body p-3 p-md-4">
                <form method="GET" action="/admin/activity_log.php" class="live-search-form" data-live-search data-results-target="#activityLogResults">
                    <div class="live-search-box" data-live-search-box>
                        <i class="fa-solid fa-magnifying-glass live-search-icon" aria-hidden="true"></i>
                        <input
                            type="text"
                            name="search"
                            class="form-control live-search-input"
                            placeholder="Search by action, details, operator, or IP address..."
                            value="${esc(data.search)}"
                            autocomplete="off"
                            aria-label="Search activity logs"
                        >
                        <div class="live-search-actions">
                            <span class="live-search-spinner" aria-hidden="true">
                                <i class="fa-solid fa-spinner fa-spin"></i>
                            </span>
                            <button
                                type="button"
                                class="live-search-clear ${data.search ? '' : 'd-none'}"
                                data-live-search-clear
                                aria-label="Clear search"
                                title="Clear search"
                            >
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div id="activityLogResults" class="card overflow-hidden live-search-results" data-live-search-results>
            ${data.html}
        </div>
    </div>`;
  return renderLayout({ title: 'Activity Logs', activeSection: 'activity_log', bodyHtml });
}

function renderBackupRecoveryPage() {
  const bodyHtml = `
    <div class="container-fluid">
        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
            <div>
                <h1 class="h3 fw-semibold mb-1">Database Backup &amp; Recovery</h1>
                <p class="font-meta text-muted small mb-0">Export live database SQL snapshots or restore previous backups in XAMPP phpMyAdmin.</p>
            </div>
        </div>

        ${renderFlash()}

        <div class="row g-4 mb-4">
            <div class="col-12 col-md-6">
                <div class="card h-100 p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="stat-icon-wrapper stat-icon-blue">
                            <i class="fa-solid fa-download"></i>
                        </div>
                        <div>
                            <h5 class="font-display fw-semibold mb-1">Export Database SQL</h5>
                            <small class="font-meta text-muted">Create an instant .sql snapshot of all items, users, and logs.</small>
                        </div>
                    </div>
                    <p class="small text-secondary mb-4">
                        Generates a complete SQL file containing all table structures and rows. You can import this file directly into MySQL or phpMyAdmin in XAMPP at any time.
                    </p>
                    <a href="/admin/backup_recovery.php?download=sql" class="btn btn-primary w-100 py-2 mt-auto">
                        <i class="fa-solid fa-cloud-arrow-down"></i>
                        <span>Download Full SQL Dump</span>
                    </a>
                </div>
            </div>

            <div class="col-12 col-md-6">
                <div class="card h-100 p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="stat-icon-wrapper stat-icon-secondary">
                            <i class="fa-solid fa-arrows-rotate"></i>
                        </div>
                        <div>
                            <h5 class="font-display fw-semibold mb-1">Restore Instructions (XAMPP)</h5>
                            <small class="font-meta text-muted">How to restore a database backup in XAMPP.</small>
                        </div>
                    </div>
                    <ol class="small text-secondary ps-3 mb-0" style="line-height: 1.8;">
                        <li>Open your browser and navigate to <strong>http://localhost/phpmyadmin/</strong>.</li>
                        <li>Click on the database <strong class="font-meta">lost_and_found_db1</strong> in the left sidebar.</li>
                        <li>Click on the <strong>Import</strong> tab at the top.</li>
                        <li>Choose your exported <strong class="font-meta">.sql</strong> backup file and click <strong>Import</strong>.</li>
                    </ol>
                </div>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header py-3 px-4">
                <h5 class="font-display fw-semibold mb-0 fs-6">Backup Logs History</h5>
            </div>
            <div class="card-body p-0">
                ${!state.backupLogs.length ? `
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-clock-rotate-left fs-1"></i>
                    <p class="font-meta mt-2 small mb-0">No backups logged yet.</p>
                </div>` : `
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">ID</th>
                                <th>Filename</th>
                                <th>Size</th>
                                <th>Action</th>
                                <th>Status</th>
                                <th>Operator</th>
                                <th class="pe-4 text-end">Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${state.backupLogs.map((b) => `
                            <tr>
                                <td class="ps-4 font-meta text-muted small">#${b.id}</td>
                                <td class="font-meta fw-semibold text-dark">${esc(b.filename)}</td>
                                <td class="small font-meta text-muted">${(b.size_bytes / 1024).toFixed(1)} KB</td>
                                <td class="small font-meta text-uppercase fw-bold text-muted">${esc(b.action)}</td>
                                <td><span class="badge status-badge-available">${esc(b.status.toUpperCase())}</span></td>
                                <td class="small fw-medium text-secondary">${esc(getUserName(b.user_id, 'Admin'))}</td>
                                <td class="pe-4 text-end small font-meta text-muted text-nowrap">${fmtDateTime(b.created_at)}</td>
                            </tr>`).join('')}
                        </tbody>
                    </table>
                </div>`}
            </div>
        </div>
    </div>`;
  return renderLayout({ title: 'Database Backup & Recovery', activeSection: 'backup_recovery', bodyHtml, extraCss: ['assets/css/dashboard.css'] });
}

function renderSystemMonitorPage() {
  const uploadsDir = path.join(__dirname, 'uploads');
  let uploadCount = 0;
  try {
    if (fs.existsSync(uploadsDir)) {
      uploadCount = fs.readdirSync(uploadsDir).filter((f) => f !== '.' && f !== '..' && f !== '.htaccess').length;
    }
  } catch (e) {}

  const bodyHtml = `
    <div class="container-fluid">
        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
            <div>
                <h1 class="h3 fw-semibold mb-1">System Diagnostics &amp; Health</h1>
                <p class="font-meta text-muted small mb-0">Live diagnostic monitor for PHP environment, MySQL connection, and filesystem permissions.</p>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card h-100 p-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="font-meta text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">PHP Engine</span>
                        <span class="badge status-badge-available">ACTIVE</span>
                    </div>
                    <h4 class="font-display fw-semibold text-dark mb-1 tabular-nums">PHP 8.2+</h4>
                    <small class="font-meta text-muted">apache2handler interface</small>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card h-100 p-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="font-meta text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">MySQL Database</span>
                        <span class="badge status-badge-available">CONNECTED</span>
                    </div>
                    <h4 class="font-display fw-semibold text-dark mb-1 tabular-nums">8.0 / MariaDB</h4>
                    <small class="font-meta text-muted">Host: localhost:3306</small>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card h-100 p-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="font-meta text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Memory Usage</span>
                        <span class="badge role-badge-staff">OPTIMAL</span>
                    </div>
                    <h4 class="font-display fw-semibold text-dark mb-1 tabular-nums">${(process.memoryUsage().rss / 1024 / 1024).toFixed(2)} MB</h4>
                    <small class="font-meta text-muted">Limit: 512M</small>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card h-100 p-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="font-meta text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Uploads Directory</span>
                        <span class="badge status-badge-available">WRITABLE</span>
                    </div>
                    <h4 class="font-display fw-semibold text-dark mb-1 tabular-nums">${uploadCount} Files</h4>
                    <small class="font-meta text-muted">Max upload: 5M</small>
                </div>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header py-3 px-4">
                <h5 class="font-display fw-semibold mb-0 fs-6">Configuration &amp; Security Checklist</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Parameter</th>
                                <th>Status / Value</th>
                                <th>Recommendation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="ps-4 fw-semibold text-dark">PHP PDO MySQL Extension</td>
                                <td><span class="badge status-badge-available">Enabled (pdo_mysql)</span></td>
                                <td class="small font-meta text-muted">Required for prepared SQL queries and XAMPP database operations.</td>
                            </tr>
                            <tr>
                                <td class="ps-4 fw-semibold text-dark">Session Management</td>
                                <td><span class="badge status-badge-available">Active</span></td>
                                <td class="small font-meta text-muted">Secure session regenerated on authentication to prevent fixation.</td>
                            </tr>
                            <tr>
                                <td class="ps-4 fw-semibold text-dark">CSRF Protection</td>
                                <td><span class="badge status-badge-available">Active (hash_equals validation)</span></td>
                                <td class="small font-meta text-muted">All POST forms protected with cryptographically secure tokens.</td>
                            </tr>
                            <tr>
                                <td class="ps-4 fw-semibold text-dark">Upload Directory Protection</td>
                                <td><span class="badge status-badge-available">Active (.htaccess blocked)</span></td>
                                <td class="small font-meta text-muted">Direct script execution disabled in uploads folder.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>`;
  return renderLayout({ title: 'System Monitor', activeSection: 'system_monitor', bodyHtml });
}

function renderSettingsPage() {
  const bodyHtml = `
    <div class="container-fluid" style="max-width: 800px;">
        <div class="mb-4">
            <h1 class="h3 fw-semibold mb-1">System Configuration Settings</h1>
            <p class="font-meta text-muted small mb-0">Manage global portal branding, institution name, and pagination limits.</p>
        </div>

        ${renderFlash()}

        <div class="card">
            <div class="card-body p-4 p-md-5">
                <form method="POST" action="/admin/settings.php" class="needs-validation" novalidate>
                    <div class="mb-4">
                        <label for="systemName" class="form-label small">Application / System Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="systemName" name="system_name" value="${esc(state.settings.system_name)}" required>
                        <div class="form-text small">Displayed in headers, titles, and login portals.</div>
                    </div>
                    <div class="mb-4">
                        <label for="organizationName" class="form-label small">Organization / Institution Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="organizationName" name="organization_name" value="${esc(state.settings.organization_name)}" required>
                        <div class="form-text small">Campus administration department or school name.</div>
                    </div>
                    <div class="mb-4">
                        <label for="itemsPerPage" class="form-label small">Items Per Page (Pagination) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="itemsPerPage" name="items_per_page" value="${esc(state.settings.items_per_page)}" min="5" max="100" required>
                        <div class="form-text small">Default number of lost and found rows shown on directory tables (5 - 100).</div>
                    </div>
                    <hr class="my-4">
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-4">Save Configuration</button>
                    </div>
                </form>
            </div>
        </div>
    </div>`;
  return renderLayout({ title: 'System Settings', activeSection: 'settings', bodyHtml });
}

function renderProfilePage() {
  const u = currentUser();
  const bodyHtml = `
    <div class="container-fluid" style="max-width: 900px;">
        <div class="mb-4">
            <h1 class="h3 fw-semibold mb-1">My Operator Profile</h1>
            <p class="font-meta text-muted small mb-0">Manage your operator name, contact email, and account password.</p>
        </div>

        ${renderFlash()}

        <div class="row g-4">
            <div class="col-12 col-md-6">
                <div class="card h-100">
                    <div class="card-body p-4 p-md-5">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="user-avatar-circle" style="width: 58px; height: 58px; font-size: 1.5rem; border-radius: 15px;">
                                ${esc(u.full_name.charAt(0).toUpperCase())}
                            </div>
                            <div>
                                <h5 class="font-display fw-semibold text-dark mb-0">${esc(u.full_name)}</h5>
                                <small class="font-meta text-muted d-block">@${esc(u.username)}</small>
                                <span class="badge role-badge-${u.role === 'admin' ? 'admin' : 'staff'} mt-1">
                                    ${esc(u.role.toUpperCase())}
                                </span>
                            </div>
                        </div>

                        <form method="POST" action="/profile/index.php">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="mb-3">
                                <label for="profileFullName" class="form-label small">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="profileFullName" name="full_name" value="${esc(u.full_name)}" required>
                            </div>
                            <div class="mb-3">
                                <label for="profileEmail" class="form-label small">Email Address</label>
                                <input type="email" class="form-control" id="profileEmail" name="email" value="${esc(u.email)}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Username (Read-Only)</label>
                                <input type="text" class="form-control bg-light" value="${esc(u.username)}" readonly disabled>
                            </div>
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary py-2">Save Profile Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6">
                <div class="card h-100">
                    <div class="card-body p-4 p-md-5">
                        <h5 class="font-display fw-semibold text-dark mb-2">Change Password</h5>
                        <p class="font-meta text-muted small mb-4">Ensure your account uses a secure password with at least 6 characters.</p>
                        <form method="POST" action="/profile/index.php">
                            <input type="hidden" name="action" value="change_password">
                            <div class="mb-3">
                                <label for="currentPassword" class="form-label small">Current Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="newPassword" class="form-label small">New Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="newPassword" name="new_password" minlength="6" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirmPassword" class="form-label small">Confirm New Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="confirmPassword" name="confirm_password" minlength="6" required>
                            </div>
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-outline-primary py-2">Update Password</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>`;
  return renderLayout({ title: 'Operator Profile', activeSection: 'profile', bodyHtml });
}

function renderNotificationsPage() {
  const u = currentUser();
  const notifications = getUserNotifications(u.id, 100, 0);
  const unreadCount = getUnreadNotificationCount(u.id);
  const latestId = notifications.reduce((max, n) => Math.max(max, n.id), 0);

  const emptyNote = u.role === 'admin'
    ? 'You will be notified automatically whenever staff operators add a new Lost Item or Found Item.'
    : 'You will be notified automatically whenever administrators add a new Lost Item or Found Item.';

  const listHtml = !notifications.length ? `
    <div class="card">
        <div class="card-body text-center py-5">
            <div class="stat-icon-wrapper stat-icon-primary mx-auto mb-3">
                <i class="fa-regular fa-bell"></i>
            </div>
            <h5 class="font-display fw-semibold text-dark mb-1">No notifications yet</h5>
            <p class="font-meta text-muted small mb-0">${emptyNote}</p>
        </div>
    </div>` : `
    <div class="notification-list">
        ${notifications.map((n) => {
          const isRead = !!n.is_read;
          const isFound = n.related_item_type === 'found_item';
          const iconClass = isFound ? 'fa-box-open' : 'fa-circle-question';
          const roleClass = n.sender_role === 'admin' ? 'role-badge-admin' : 'role-badge-staff';
          return `
        <div class="notification-card ${isRead ? 'is-read' : 'is-unread'}" data-notification-id="${n.id}">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div class="d-flex align-items-start gap-3 min-w-0">
                    <div class="notification-icon-wrap">
                        <i class="fa-solid ${iconClass}"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            ${!isRead ? '<span class="notification-unread-dot" title="Unread" data-unread-dot></span>' : ''}
                            <h6 class="font-display fw-semibold text-dark mb-0">${esc(n.title)}</h6>
                            <span class="badge ${roleClass}">${esc(n.sender_role.toUpperCase())}</span>
                            <span class="notification-type-badge">
                                <i class="fa-solid ${iconClass}"></i>
                                <span>${esc(n.related_item_label)}</span>
                            </span>
                        </div>
                        <p class="small text-dark mb-2">
                            ${esc(n.message)}
                            <span class="text-muted">&middot; Item: <strong class="text-dark">${esc(n.related_item_name)}</strong></span>
                        </p>
                        <div class="d-flex flex-wrap align-items-center gap-3 small font-meta text-muted">
                            <span><i class="fa-solid fa-user me-1"></i>${esc(n.sender_name)}</span>
                            <span><i class="fa-regular fa-clock me-1"></i>${esc(n.created_at_formatted)}</span>
                            <span class="fw-semibold ${isRead ? 'text-muted' : 'text-primary'}" data-read-status-label>
                                ${isRead ? 'Read' : 'Unread'}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2 flex-shrink-0">
                    <a href="${esc(n.open_url)}" class="btn btn-soft-primary btn-sm px-3">
                        <i class="fa-solid fa-eye"></i>
                        <span>View Item</span>
                    </a>
                    ${!isRead ? `
                    <form method="POST" action="/notifications/index.php" class="d-inline" data-mark-read-form>
                        <input type="hidden" name="action" value="mark_read">
                        <input type="hidden" name="notification_id" value="${n.id}">
                        <button type="submit" class="btn btn-outline-secondary btn-sm px-3" data-mark-read-btn="${n.id}">
                            <i class="fa-solid fa-check"></i>
                            <span>Mark Read</span>
                        </button>
                    </form>` : ''}
                </div>
            </div>
        </div>`;
        }).join('')}
    </div>`;

  const bodyHtml = `
    <div class="container-fluid" style="max-width: 960px;" data-notifications-page data-latest-id="${latestId}">
        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h1 class="h3 fw-semibold mb-0">Notifications</h1>
                    <span class="badge ${unreadCount > 0 ? 'status-badge-matched' : 'status-badge-inactive'}" id="pageUnreadBadge">
                        <span data-page-unread-count>${unreadCount}</span> Unread
                    </span>
                </div>
                <p class="font-meta text-muted small mb-0">
                    Role-based alerts for newly reported lost items and found custody records.
                </p>
            </div>

            <form method="POST" action="/notifications/index.php" class="d-inline" data-mark-all-read-form>
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-outline-primary btn-sm px-3 py-2 ${unreadCount === 0 ? 'disabled' : ''}" data-mark-all-read-btn ${unreadCount === 0 ? 'disabled' : ''}>
                    <i class="fa-solid fa-check-double"></i>
                    <span>Mark All as Read</span>
                </button>
            </form>
        </div>

        ${renderFlash()}

        <div id="notificationsListContainer">
            ${listHtml}
        </div>
    </div>`;

  return renderLayout({ title: 'Notifications', activeSection: 'notifications', bodyHtml });
}

const server = http.createServer(async (req, res) => {
  const parsedUrl = new URL(req.url, `http://${req.headers.host || 'localhost'}`);
  const reqPath = parsedUrl.pathname;
  const query = Object.fromEntries(parsedUrl.searchParams.entries());

  // Serve favicon.ico directly
  if (reqPath === '/favicon.ico') {
    const favPath = path.join(__dirname, 'assets', 'aistudio', 'image_8911a931.png');
    if (fs.existsSync(favPath) && fs.statSync(favPath).isFile()) {
      res.writeHead(200, { 'Content-Type': 'image/png', 'Cache-Control': 'no-cache, must-revalidate' });
      fs.createReadStream(favPath).pipe(res);
      return;
    }
  }

  // Serve static assets (css, js, images)
  if (reqPath.startsWith('/assets/') || reqPath.startsWith('/uploads/') || reqPath.startsWith('/picture/')) {
    const resolvedPath = path.resolve(__dirname, '.' + reqPath);
    const allowedRoots = [
      path.join(__dirname, 'assets') + path.sep,
      path.join(__dirname, 'uploads') + path.sep,
      path.join(__dirname, 'picture') + path.sep,
    ];
    const isAllowed = allowedRoots.some((root) => resolvedPath.startsWith(root));
    if (isAllowed && fs.existsSync(resolvedPath) && fs.statSync(resolvedPath).isFile()) {
      const ext = path.extname(resolvedPath).toLowerCase();
      const headers = { 'Content-Type': mimeTypes[ext] || 'application/octet-stream' };
      if (ext === '.css' || ext === '.js') {
        headers['Cache-Control'] = 'no-cache, must-revalidate';
      }
      res.writeHead(200, headers);
      fs.createReadStream(resolvedPath).pipe(res);
      return;
    }
    res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
    res.end('Not Found');
    return;
  }

  // Dynamic routes run inside per-browser cookie session scope with no-store cache headers
  const sess = getOrCreateSession(req, res);
  res.setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private');
  res.setHeader('Pragma', 'no-cache');

  return sessionStore.run(sess, async () => {
    // Logout
    if (reqPath === '/logout.php') {
      const u = currentUser();
      if (u) logActivity('LOGOUT', 'users', u.id, `User ${u.username} logged out`);
      sess.userId = null;
      saveState();
      res.setHeader('Set-Cookie', buildSessionCookie(req, sess.id));
      setFlash('info', 'You have been successfully logged out.');
      return redirect(res, '/login.php');
    }

    // Login (always stays on login page on GET/refresh)
    if (reqPath === '/login.php') {
      if (req.method === 'POST') {
        const body = await parseBody(req);
        const username = (body.username || '').trim();
        const password = body.password || '';
        if (!username || !password) {
          res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
          return res.end(renderLoginPage('Please enter both username and password.', username));
        }
        const user = state.users.find((u) => u.username === username);
        if (!user || user.password !== password) {
          logActivity('FAILED_LOGIN', 'users', null, `Failed login attempt for username: ${username}`);
          saveState();
          res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
          return res.end(renderLoginPage('Invalid username or password.', username));
        }
        if (user.status !== 'active') {
          res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
          return res.end(renderLoginPage('Your account has been deactivated. Please contact an administrator.', username));
        }
        sess.userId = user.id;
        sess.expiresAt = Date.now() + SESSION_TTL_MS;
        user.last_login = nowStr();
        logActivity('LOGIN', 'users', user.id, `User ${user.username} logged in successfully`);
        saveState();
        res.setHeader('Set-Cookie', buildSessionCookie(req, sess.id));
        setFlash('success', `Welcome back, ${user.full_name}!`);
        return redirect(res, '/dashboard.php');
      }
      res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
      return res.end(renderLoginPage());
    }

    // Root entry point: open system at login when unauthenticated, dashboard when logged in
    if (reqPath === '/' || reqPath === '/index.php' || reqPath === '/index.html') {
      if (!currentUser()) {
        return redirect(res, '/login.php');
      }
      return redirect(res, '/dashboard.php');
    }

    // API auth check (return 401 JSON instead of HTML redirect when unauthenticated)
    if (reqPath === '/notifications/api.php' && !currentUser()) {
      res.writeHead(401, { 'Content-Type': 'application/json; charset=utf-8' });
      return res.end(JSON.stringify({ ok: false, error: 'Authentication required.' }));
    }

    // Require login for app routes
    if (!currentUser()) {
      setFlash('error', 'Please log in to access this page.');
      return redirect(res, '/login.php');
    }

    // Dashboard
    if (reqPath === '/dashboard.php') {
      res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
      return res.end(renderDashboardPage());
    }

  // Lost Items
  if (reqPath === '/lost_items/index.php') {
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    return res.end(renderLostItemsIndex(query));
  }
  if (reqPath === '/lost_items/report.php') {
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    return res.end(renderReportLostPage());
  }
  if (reqPath === '/lost_items/view.php') {
    const html = renderLostItemView(query.id);
    if (!html) {
      setFlash('error', 'Lost item record not found.');
      return redirect(res, '/lost_items/index.php');
    }
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    return res.end(html);
  }
  if (reqPath === '/lost_items/edit.php') {
    const html = renderLostItemEdit(query.id);
    if (!html) {
      setFlash('error', 'Lost item record not found.');
      return redirect(res, '/lost_items/index.php');
    }
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    return res.end(html);
  }
  if (reqPath === '/lost_items/process.php' && req.method === 'POST') {
    const body = await parseBody(req);
    const action = body.action || '';
    if (action === 'create') {
      const itemName = (body.item_name || '').trim();
      const dateLost = (body.date_lost || '').trim();
      const locationLost = (body.location_lost || '').trim();
      const description = (body.description || '').trim();
      const contactInfo = (body.contact_info || '').trim();
      if (!itemName || !dateLost || !locationLost || !description || !contactInfo) {
        setFlash('error', 'Please fill in all required fields.');
        return redirect(res, '/lost_items/report.php');
      }
      const imgFile = body._files && body._files.image;
      const uploadRes = imgFile ? saveUploadedImage(imgFile) : { ok: true, path: null };
      if (!uploadRes.ok) {
        setFlash('error', uploadRes.error);
        return redirect(res, '/lost_items/report.php');
      }
      const newItem = {
        id: state.nextIds.lost++,
        item_name: itemName,
        category_id: Number(body.category_id) || 10,
        description,
        date_lost: dateLost,
        location_lost: locationLost,
        contact_info: contactInfo,
        image_path: uploadRes.path,
        status: 'open',
        reported_by: currentUser().id,
        created_at: nowStr(),
        updated_at: null,
      };
      state.lostItems.unshift(newItem);
      createRoleItemNotifications(currentUser(), 'lost_item', newItem.id);
      logActivity('CREATE_LOST_ITEM', 'lost_items', newItem.id, `Reported lost item: ${itemName}`);
      saveState();
      setFlash('success', `Lost item '${itemName}' recorded successfully.`);
      return redirect(res, `/lost_items/view.php?id=${newItem.id}`);
    }
    if (action === 'update') {
      const id = Number(body.id || 0);
      const item = state.lostItems.find((i) => i.id === id);
      if (!item) return redirect(res, '/lost_items/index.php');

      const itemName = (body.item_name || '').trim();
      const dateLost = (body.date_lost || '').trim();
      const locationLost = (body.location_lost || '').trim();
      const description = (body.description || '').trim();
      const contactInfo = (body.contact_info || '').trim();
      if (!itemName || !dateLost || !locationLost || !description || !contactInfo) {
        setFlash('error', 'Invalid input data. Please fill in all required fields.');
        return redirect(res, `/lost_items/edit.php?id=${id}`);
      }

      const imgFile = body._files && body._files.image;
      const uploadRes = imgFile ? saveUploadedImage(imgFile) : { ok: true, path: null };
      if (!uploadRes.ok) {
        setFlash('error', uploadRes.error);
        return redirect(res, `/lost_items/edit.php?id=${id}`);
      }

      const oldImage = item.image_path;
      item.item_name = itemName;
      item.category_id = Number(body.category_id) || item.category_id;
      item.date_lost = dateLost;
      item.location_lost = locationLost;
      item.description = description;
      item.contact_info = contactInfo;
      item.status = ['open', 'matched', 'returned', 'closed'].includes(body.status) ? body.status : item.status;
      if (uploadRes.path) {
        item.image_path = uploadRes.path;
        if (oldImage && oldImage !== uploadRes.path) {
          deleteUploadedImage(oldImage);
        }
      }
      item.updated_at = nowStr();
      logActivity('UPDATE_LOST_ITEM', 'lost_items', id, `Updated lost item #${id} (${item.item_name}) status: ${item.status}`);
      saveState();
      setFlash('success', 'Lost item updated successfully.');
      return redirect(res, `/lost_items/view.php?id=${id}`);
    }
    if (action === 'delete') {
      const id = Number(body.id || 0);
      const target = state.lostItems.find((i) => i.id === id);
      if (target && target.image_path) {
        deleteUploadedImage(target.image_path);
      }
      state.lostItems = state.lostItems.filter((i) => i.id !== id);
      state.matches = state.matches.filter((m) => m.lost_item_id !== id);
      logActivity('DELETE_LOST_ITEM', 'lost_items', id, `Deleted lost item record #${id}`);
      saveState();
      setFlash('success', 'Lost item record deleted.');
      return redirect(res, '/lost_items/index.php');
    }
    return redirect(res, '/lost_items/index.php');
  }

  // Found Items
  if (reqPath === '/found_items/index.php') {
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    return res.end(renderFoundItemsIndex(query));
  }
  if (reqPath === '/found_items/add.php') {
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    return res.end(renderFoundAddPage());
  }
  if (reqPath === '/found_items/view.php') {
    const html = renderFoundItemView(query.id);
    if (!html) {
      setFlash('error', 'Found item record not found.');
      return redirect(res, '/found_items/index.php');
    }
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    return res.end(html);
  }
  if (reqPath === '/found_items/edit.php') {
    const html = renderFoundItemEdit(query.id);
    if (!html) {
      setFlash('error', 'Found item record not found.');
      return redirect(res, '/found_items/index.php');
    }
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    return res.end(html);
  }
  if (reqPath === '/found_items/process.php' && req.method === 'POST') {
    const body = await parseBody(req);
    const action = body.action || '';
    if (action === 'create') {
      const itemName = (body.item_name || '').trim();
      const dateFound = (body.date_found || '').trim();
      const locationFound = (body.location_found || '').trim();
      const storageLocation = (body.storage_location || '').trim();
      const description = (body.description || '').trim();
      if (!itemName || !dateFound || !locationFound || !storageLocation || !description) {
        setFlash('error', 'Please fill in all required fields.');
        return redirect(res, '/found_items/add.php');
      }
      const imgFile = body._files && body._files.image;
      const uploadRes = imgFile ? saveUploadedImage(imgFile) : { ok: true, path: null };
      if (!uploadRes.ok) {
        setFlash('error', uploadRes.error);
        return redirect(res, '/found_items/add.php');
      }
      const newItem = {
        id: state.nextIds.found++,
        item_name: itemName,
        category_id: Number(body.category_id) || 10,
        description,
        date_found: dateFound,
        location_found: locationFound,
        storage_location: storageLocation,
        notes: (body.notes || '').trim(),
        image_path: uploadRes.path,
        status: 'available',
        recorded_by: currentUser().id,
        created_at: nowStr(),
        updated_at: null,
      };
      state.foundItems.unshift(newItem);
      createRoleItemNotifications(currentUser(), 'found_item', newItem.id);
      logActivity('CREATE_FOUND_ITEM', 'found_items', newItem.id, `Recorded found item: ${itemName} (Storage: ${storageLocation})`);
      saveState();
      setFlash('success', `Found item '${itemName}' recorded into storage.`);
      return redirect(res, `/found_items/view.php?id=${newItem.id}`);
    }
    if (action === 'update') {
      const id = Number(body.id || 0);
      const item = state.foundItems.find((i) => i.id === id);
      if (!item) return redirect(res, '/found_items/index.php');

      const itemName = (body.item_name || '').trim();
      const dateFound = (body.date_found || '').trim();
      const locationFound = (body.location_found || '').trim();
      const storageLocation = (body.storage_location || '').trim();
      const description = (body.description || '').trim();
      if (!itemName || !dateFound || !locationFound || !storageLocation || !description) {
        setFlash('error', 'Invalid input data. Please fill in all required fields.');
        return redirect(res, `/found_items/edit.php?id=${id}`);
      }

      const imgFile = body._files && body._files.image;
      const uploadRes = imgFile ? saveUploadedImage(imgFile) : { ok: true, path: null };
      if (!uploadRes.ok) {
        setFlash('error', uploadRes.error);
        return redirect(res, `/found_items/edit.php?id=${id}`);
      }

      const oldImage = item.image_path;
      item.item_name = itemName;
      item.category_id = Number(body.category_id) || item.category_id;
      item.date_found = dateFound;
      item.location_found = locationFound;
      item.storage_location = storageLocation;
      item.description = description;
      item.notes = (body.notes || '').trim();
      item.status = ['available', 'matched', 'returned', 'closed'].includes(body.status) ? body.status : item.status;
      if (uploadRes.path) {
        item.image_path = uploadRes.path;
        if (oldImage && oldImage !== uploadRes.path) {
          deleteUploadedImage(oldImage);
        }
      }
      item.updated_at = nowStr();
      logActivity('UPDATE_FOUND_ITEM', 'found_items', id, `Updated found item #${id} (${item.item_name}) status: ${item.status}`);
      saveState();
      setFlash('success', 'Found item record updated.');
      return redirect(res, `/found_items/view.php?id=${id}`);
    }
    if (action === 'delete') {
      const id = Number(body.id || 0);
      const target = state.foundItems.find((i) => i.id === id);
      if (target && target.image_path) {
        deleteUploadedImage(target.image_path);
      }
      state.foundItems = state.foundItems.filter((i) => i.id !== id);
      state.matches = state.matches.filter((m) => m.found_item_id !== id);
      logActivity('DELETE_FOUND_ITEM', 'found_items', id, `Deleted found item record #${id}`);
      saveState();
      setFlash('success', 'Found item record deleted.');
      return redirect(res, '/found_items/index.php');
    }
    return redirect(res, '/found_items/index.php');
  }

  // Matches
  if (reqPath === '/matches/index.php') {
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    return res.end(renderMatchesPage());
  }
  if (reqPath === '/matches/process.php' && req.method === 'POST') {
    const body = await parseBody(req);
    const action = body.action || '';
    if (action === 'create') {
      const lostId = Number(body.lost_item_id || 0);
      const foundId = Number(body.found_item_id || 0);
      const lost = state.lostItems.find((l) => l.id === lostId);
      const found = state.foundItems.find((f) => f.id === foundId);
      if (!lost || !found || lost.status !== 'open' || found.status !== 'available') {
        setFlash('error', 'Selected items must be open (lost) and available (found) to create a match.');
        return redirect(res, '/matches/index.php');
      }
      const match = {
        id: state.nextIds.match++,
        lost_item_id: lostId,
        found_item_id: foundId,
        confirmed_by: currentUser().id,
        notes: (body.notes || '').trim(),
        created_at: nowStr(),
      };
      state.matches.unshift(match);
      lost.status = 'matched';
      lost.updated_at = nowStr();
      found.status = 'matched';
      found.updated_at = nowStr();
      logActivity('CREATE_MATCH', 'matches', match.id, `Created match #${match.id} (Lost #${lostId} <-> Found #${foundId})`);
      saveState();
      setFlash('success', 'Match created successfully. Both items updated to Matched status.');
      return redirect(res, '/matches/index.php');
    }
    if (action === 'mark_returned') {
      const matchId = Number(body.match_id || 0);
      const match = state.matches.find((m) => m.id === matchId);
      if (match) {
        const lost = state.lostItems.find((l) => l.id === match.lost_item_id);
        const found = state.foundItems.find((f) => f.id === match.found_item_id);
        if (lost) { lost.status = 'returned'; lost.updated_at = nowStr(); }
        if (found) { found.status = 'returned'; found.updated_at = nowStr(); }
        logActivity('RETURN_ITEM', 'matches', matchId, `Marked match #${matchId} as claimed & returned to owner`);
        saveState();
        setFlash('success', 'Item marked as successfully returned to owner.');
      }
      return redirect(res, '/matches/index.php');
    }
    if (action === 'delete') {
      const matchId = Number(body.match_id || 0);
      const match = state.matches.find((m) => m.id === matchId);
      if (match) {
        const lost = state.lostItems.find((l) => l.id === match.lost_item_id);
        const found = state.foundItems.find((f) => f.id === match.found_item_id);
        if (lost && lost.status === 'matched') { lost.status = 'open'; lost.updated_at = nowStr(); }
        if (found && found.status === 'matched') { found.status = 'available'; found.updated_at = nowStr(); }
        state.matches = state.matches.filter((m) => m.id !== matchId);
        logActivity('DELETE_MATCH', 'matches', matchId, `Unlinked match #${matchId}`);
        saveState();
        setFlash('success', 'Match unlinked. Items restored to open/available status.');
      }
      return redirect(res, '/matches/index.php');
    }
    return redirect(res, '/matches/index.php');
  }

  // Profile
  if (reqPath === '/profile/index.php') {
    if (req.method === 'POST') {
      const body = await parseBody(req);
      const u = currentUser();
      if (body.action === 'update_profile') {
        const fullName = (body.full_name || '').trim();
        if (fullName) {
          u.full_name = fullName;
          u.email = (body.email || '').trim();
          logActivity('UPDATE_PROFILE', 'users', u.id, 'Updated personal profile information');
          saveState();
          setFlash('success', 'Profile information updated successfully.');
        } else {
          setFlash('error', 'Full name cannot be empty.');
        }
      } else if (body.action === 'change_password') {
        const curr = body.current_password || '';
        const next = body.new_password || '';
        const conf = body.confirm_password || '';
        if (!curr || !next) setFlash('error', 'Please fill in all password fields.');
        else if (next !== conf) setFlash('error', 'New password and confirmation do not match.');
        else if (next.length < 6) setFlash('error', 'New password must be at least 6 characters.');
        else if (curr !== u.password) setFlash('error', 'Current password is incorrect.');
        else {
          u.password = next;
          logActivity('CHANGE_PASSWORD', 'users', u.id, 'Updated account security password');
          saveState();
          setFlash('success', 'Password updated successfully.');
        }
      }
      return redirect(res, '/profile/index.php');
    }
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    return res.end(renderProfilePage());
  }

  // Notifications API (Polling & Read State)
  if (reqPath === '/notifications/api.php') {
    const cu = currentUser();
    if (req.method === 'POST') {
      const body = await parseBody(req);
      const action = body.action || '';
      if (action === 'mark_read') {
        const notifId = Number(body.notification_id || 0);
        const notif = state.notifications.find((n) => n.id === notifId);
        if (!notif) {
          res.writeHead(404, { 'Content-Type': 'application/json; charset=utf-8' });
          return res.end(JSON.stringify({ ok: false, error: 'Notification not found.' }));
        }
        if (notif.recipient_user_id !== cu.id) {
          res.writeHead(403, { 'Content-Type': 'application/json; charset=utf-8' });
          return res.end(JSON.stringify({ ok: false, error: 'You do not have permission to modify this notification.' }));
        }
        notif.is_read = 1;
        notif.read_at = notif.read_at || nowStr();
        saveState();
        res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
        return res.end(JSON.stringify({ ok: true, unreadCount: getUnreadNotificationCount(cu.id) }));
      }
      if (action === 'mark_all_read') {
        const ts = nowStr();
        for (const n of state.notifications) {
          if (n.recipient_user_id === cu.id && !n.is_read) {
            n.is_read = 1;
            n.read_at = ts;
          }
        }
        saveState();
        res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
        return res.end(JSON.stringify({ ok: true, unreadCount: 0 }));
      }
      res.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
      return res.end(JSON.stringify({ ok: false, error: 'Unsupported action.' }));
    }

    const lastId = Math.max(0, parseInt(query.last_id || '0', 10) || 0);
    const includeList = query.include_list === '1';
    const userNotifs = state.notifications.filter((n) => n.recipient_user_id === cu.id);
    const latestId = userNotifs.reduce((max, n) => Math.max(max, n.id), 0);
    const newNotifications = (lastId > 0 && latestId > lastId) ? getUserNotifications(cu.id, 15, lastId) : [];

    const payload = {
      ok: true,
      userId: cu.id,
      unreadCount: getUnreadNotificationCount(cu.id),
      latestId,
      newNotifications,
    };
    if (includeList) {
      payload.notifications = getUserNotifications(cu.id, 100, 0);
    }
    res.writeHead(200, {
      'Content-Type': 'application/json; charset=utf-8',
      'Cache-Control': 'no-store, no-cache, must-revalidate',
    });
    return res.end(JSON.stringify(payload));
  }

  // Notifications Page
  if (reqPath === '/notifications/index.php') {
    const cu = currentUser();
    if (query.open) {
      const openId = Number(query.open);
      const notif = state.notifications.find((n) => n.id === openId);
      if (!notif) {
        setFlash('error', 'Notification not found.');
        return redirect(res, '/notifications/index.php');
      }
      if (notif.recipient_user_id !== cu.id) {
        setFlash('error', 'You do not have permission to open that notification.');
        return redirect(res, '/notifications/index.php');
      }
      notif.is_read = 1;
      notif.read_at = notif.read_at || nowStr();
      saveState();
      const targetUrl = notif.related_item_type === 'found_item'
        ? `/found_items/view.php?id=${notif.related_item_id}`
        : `/lost_items/view.php?id=${notif.related_item_id}`;
      return redirect(res, targetUrl);
    }

    if (req.method === 'POST') {
      const body = await parseBody(req);
      if (body.action === 'mark_read') {
        const notifId = Number(body.notification_id || 0);
        const notif = state.notifications.find((n) => n.id === notifId && n.recipient_user_id === cu.id);
        if (notif) {
          notif.is_read = 1;
          notif.read_at = notif.read_at || nowStr();
          saveState();
        }
      } else if (body.action === 'mark_all_read') {
        const ts = nowStr();
        for (const n of state.notifications) {
          if (n.recipient_user_id === cu.id && !n.is_read) {
            n.is_read = 1;
            n.read_at = ts;
          }
        }
        saveState();
        setFlash('success', 'All notifications marked as read.');
      }
      return redirect(res, '/notifications/index.php');
    }

    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    return res.end(renderNotificationsPage());
  }

  // Admin routes
  if (reqPath.startsWith('/admin/')) {
    if (!isAdmin()) {
      setFlash('error', 'Access denied. Administrator privileges required.');
      return redirect(res, '/dashboard.php');
    }
    if (reqPath === '/admin/manage_users.php') {
      if (req.method === 'POST') {
        const body = await parseBody(req);
        if (body.action === 'create_user') {
          const fullName = (body.full_name || '').trim();
          const username = (body.username || '').trim();
          const email = (body.email || '').trim();
          const password = body.password || '';
          const role = body.role === 'admin' ? 'admin' : 'staff';
          if (!fullName || !username || !password) {
            setFlash('error', 'Please fill in all required user fields.');
          } else if (state.users.some((x) => x.username === username)) {
            setFlash('error', 'Failed to create user (username already exists).');
          } else {
            const newUser = {
              id: state.nextIds.user++,
              full_name: fullName,
              username,
              email,
              password,
              role,
              status: 'active',
              last_login: null,
              created_at: nowStr(),
            };
            state.users.unshift(newUser);
            logActivity('CREATE_USER', 'users', newUser.id, `Created operator account @${username} with role ${role}`);
            saveState();
            setFlash('success', `Operator account @${username} created successfully.`);
          }
        } else if (body.action === 'toggle_status') {
          const uid = Number(body.user_id || 0);
          if (uid === currentUser().id) {
            setFlash('error', 'You cannot change the status of your own account.');
          } else {
            const target = state.users.find((x) => x.id === uid);
            if (target) {
              target.status = body.status === 'active' ? 'active' : 'inactive';
              logActivity('UPDATE_USER_STATUS', 'users', uid, `Changed status of user #${uid} to ${target.status}`);
              saveState();
              setFlash('success', `User account status updated to ${target.status}.`);
            }
          }
        } else if (body.action === 'delete_user') {
          const uid = Number(body.user_id || 0);
          if (uid === currentUser().id) {
            setFlash('error', 'You cannot delete your own account.');
          } else {
            state.users = state.users.filter((x) => x.id !== uid);
            logActivity('DELETE_USER', 'users', uid, `Deleted user #${uid}`);
            saveState();
            setFlash('success', 'User account deleted successfully.');
          }
        }
        return redirect(res, '/admin/manage_users.php');
      }
      res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
      return res.end(renderManageUsersPage());
    }
    if (reqPath === '/admin/activity_log.php') {
      if (query.ajax === '1') {
        const data = buildActivityLogData(query);
        res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
        return res.end(JSON.stringify({
          ok: true,
          search: data.search,
          page: data.page,
          totalPages: data.totalPages,
          totalLogs: data.totalLogs,
          html: data.html,
        }));
      }
      res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
      return res.end(renderActivityLogPage(query));
    }
    if (reqPath === '/admin/backup_recovery.php') {
      if (query.download === 'sql') {
        const sqlFile = path.join(__dirname, 'database', 'lost_and_found_db.sql');
        const dump = fs.existsSync(sqlFile) ? fs.readFileSync(sqlFile, 'utf8') : '-- FoundU SQL Backup\n';
        const filename = `foundu_backup_${todayStr()}.sql`;
        const bLog = {
          id: state.nextIds.backup++,
          user_id: currentUser().id,
          filename,
          size_bytes: Buffer.byteLength(dump, 'utf8'),
          action: 'backup',
          status: 'success',
          created_at: nowStr(),
        };
        state.backupLogs.unshift(bLog);
        logActivity('EXPORT_BACKUP', 'backup_logs', bLog.id, `Generated database backup ${filename} (${bLog.size_bytes} bytes)`);
        saveState();
        res.writeHead(200, {
          'Content-Type': 'application/sql',
          'Content-Disposition': `attachment; filename="${filename}"`,
        });
        return res.end(dump);
      }
      res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
      return res.end(renderBackupRecoveryPage());
    }
    if (reqPath === '/admin/system_monitor.php') {
      res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
      return res.end(renderSystemMonitorPage());
    }
    if (reqPath === '/admin/settings.php') {
      if (req.method === 'POST') {
        const body = await parseBody(req);
        const sysName = (body.system_name || '').trim();
        const orgName = (body.organization_name || '').trim();
        const perPage = Math.max(5, Math.min(100, parseInt(body.items_per_page || '15', 10) || 15));
        if (sysName && orgName) {
          state.settings.system_name = sysName;
          state.settings.organization_name = orgName;
          state.settings.items_per_page = String(perPage);
          logActivity('UPDATE_SETTINGS', 'system_settings', null, `Updated system settings (Name: ${sysName})`);
          saveState();
          setFlash('success', 'System settings updated successfully.');
        } else {
          setFlash('error', 'Please fill in all required settings fields.');
        }
        return redirect(res, '/admin/settings.php');
      }
      res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
      return res.end(renderSettingsPage());
    }
  }

    // Fallback to dashboard
    return redirect(res, '/dashboard.php');
  });
});

server.listen(PORT, HOST, () => {
  console.log(`FoundU preview server running at http://${HOST}:${PORT}/`);
});
