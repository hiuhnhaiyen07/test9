(function () {
  const DEFAULT_CONFIG = {
    site_name: 'Premium Service',
    site_tagline: 'Dịch vụ số xử lý qua Gmail, webhook và Telegram admin',
    price: 29000,
    plan_name: '29.000đ / 1 tháng',
    bank_name: 'MB Bank',
    bank_account_name: 'LE KIM YEN',
    bank_account_number: '610793',
    support_link: 'https://t.me/your_support',
    support_label: 'Telegram hỗ trợ',
    qr_image_path: 'img/mbbank-qr.png',
    hero_badge: 'Webhook xác nhận tự động',
    cta_subtitle: 'Nhập Gmail, thanh toán QR, admin nhận Telegram để xử lý.',
    status_poll_interval_ms: 5000,
  };

  const state = {
    config: { ...DEFAULT_CONFIG },
    formStartedAt: Math.floor(Date.now() / 1000),
  };

  const sparkles = document.getElementById('sparkles');
  const toast = document.getElementById('toast');

  function formatMoney(value) {
    return new Intl.NumberFormat('vi-VN').format(Number(value || 0)) + 'đ';
  }

  function createSparkles() {
    if (!sparkles) return;
    const count = window.innerWidth < 768 ? 18 : 34;
    sparkles.innerHTML = '';
    for (let i = 0; i < count; i += 1) {
      const el = document.createElement('span');
      el.className = 'sparkle';
      el.style.left = Math.random() * 100 + '%';
      el.style.bottom = -Math.random() * 30 + 'vh';
      el.style.animationDuration = 10 + Math.random() * 9 + 's';
      el.style.animationDelay = Math.random() * 6 + 's';
      el.style.opacity = String(0.28 + Math.random() * 0.5);
      sparkles.appendChild(el);
    }
  }

  function showToast(message) {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    clearTimeout(showToast._timer);
    showToast._timer = setTimeout(() => toast.classList.remove('show'), 2400);
  }

  function safeJson(response) {
    return response.text().then((text) => {
      try {
        return JSON.parse(text);
      } catch (error) {
        throw new Error(text || 'Phản hồi máy chủ không hợp lệ.');
      }
    });
  }

  async function fetchPublicConfig() {
    try {
      const response = await fetch('api/public_config.php', { headers: { Accept: 'application/json' } });
      const data = await safeJson(response);
      if (data.ok && data.config) {
        state.config = { ...state.config, ...data.config };
      }
    } catch (error) {
      console.error(error);
    }
  }

  function applyPublicConfig() {
    document.querySelectorAll('[data-config="site_name"]').forEach((el) => {
      el.textContent = state.config.site_name;
    });
    document.querySelectorAll('[data-config="site_tagline"]').forEach((el) => {
      el.textContent = state.config.site_tagline;
    });
    document.querySelectorAll('[data-config="plan_name"]').forEach((el) => {
      el.textContent = state.config.plan_name;
    });
    document.querySelectorAll('[data-config="bank_name"]').forEach((el) => {
      el.textContent = state.config.bank_name;
    });
    document.querySelectorAll('[data-config="bank_account_name"]').forEach((el) => {
      el.textContent = state.config.bank_account_name;
    });
    document.querySelectorAll('[data-config="bank_account_number"]').forEach((el) => {
      el.textContent = state.config.bank_account_number;
    });
    document.querySelectorAll('[data-config="hero_badge"]').forEach((el) => {
      el.textContent = state.config.hero_badge;
    });
    document.querySelectorAll('[data-config="cta_subtitle"]').forEach((el) => {
      el.textContent = state.config.cta_subtitle;
    });
    document.querySelectorAll('[data-price-text]').forEach((el) => {
      el.textContent = formatMoney(state.config.price);
    });
    document.querySelectorAll('.support-link').forEach((link) => {
      link.setAttribute('href', state.config.support_link || '#');
      if (link.textContent.trim() === 'Hỗ trợ' || link.textContent.trim() === 'Nhắn hỗ trợ' || link.textContent.trim() === '💬 Hỗ trợ ngay') {
        if (link.classList.contains('floating-support')) {
          link.textContent = '💬 ' + (state.config.support_label || 'Hỗ trợ');
        } else if (link.classList.contains('primary-btn') || link.classList.contains('ghost-btn')) {
          link.textContent = state.config.support_label || 'Hỗ trợ';
        }
      }
    });
    const qrImage = document.getElementById('qrImage');
    if (qrImage) {
      qrImage.src = state.config.qr_image_path;
    }
    if (document.title.includes('Premium Service')) {
      document.title = document.title.replace('Premium Service', state.config.site_name || 'Premium Service');
    }
  }

  function isValidGmail(email) {
    return /^[^\s@]+@gmail\.com$/i.test((email || '').trim());
  }

  function getQueryParam(name) {
    return new URL(window.location.href).searchParams.get(name);
  }

  function saveLastOrder(order) {
    localStorage.setItem('lastOrder', JSON.stringify(order));
  }

  function readLastOrder() {
    try {
      return JSON.parse(localStorage.getItem('lastOrder') || 'null');
    } catch (error) {
      return null;
    }
  }

  async function createOrder(payload) {
    const response = await fetch('api/create_order.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    return safeJson(response);
  }

  async function checkOrder(orderId) {
    const response = await fetch('api/order_status.php?order_id=' + encodeURIComponent(orderId), {
      headers: { Accept: 'application/json' },
    });
    return safeJson(response);
  }

  function initLanding() {
    const form = document.getElementById('orderForm');
    if (!form) return;

    const input = document.getElementById('email');
    const note = document.getElementById('note');
    const error = document.getElementById('formError');
    const websiteTrap = document.getElementById('websiteTrap');

    const cached = readLastOrder();
    if (cached && cached.email) {
      input.value = cached.email;
    }

    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      error.hidden = true;

      const email = input.value.trim();
      const noteText = note.value.trim();
      const website = websiteTrap ? websiteTrap.value.trim() : '';

      if (!isValidGmail(email)) {
        error.textContent = 'Vui lòng nhập đúng địa chỉ Gmail với đuôi @gmail.com.';
        error.hidden = false;
        input.focus();
        return;
      }

      const button = form.querySelector('button[type="submit"]');
      const originalText = button.textContent;
      button.disabled = true;
      button.textContent = 'Đang tạo đơn...';

      try {
        const result = await createOrder({
          email,
          note: noteText,
          website,
          started_at: state.formStartedAt,
        });
        if (!result.ok) {
          throw new Error(result.message || 'Không tạo được đơn hàng.');
        }
        saveLastOrder(result.order);
        window.location.href = 'payment.html?order_id=' + encodeURIComponent(result.order.order_id);
      } catch (err) {
        error.textContent = err.message || 'Có lỗi xảy ra, vui lòng thử lại.';
        error.hidden = false;
      } finally {
        button.disabled = false;
        button.textContent = originalText;
      }
    });
  }

  function updatePaymentTimeline(order) {
    const waiting = document.getElementById('statusWaiting');
    const done = document.getElementById('statusSuccess');
    const orderIdText = document.getElementById('orderIdText');
    if (orderIdText) {
      orderIdText.textContent = order && order.order_id ? 'Mã đơn: ' + order.order_id : 'Chưa có mã đơn';
    }

    const finalStates = ['paid', 'processing', 'completed'];
    const isDone = order && finalStates.includes(order.status);

    if (waiting) {
      waiting.classList.toggle('active', !isDone);
      waiting.classList.toggle('done', isDone);
    }
    if (done) {
      done.classList.toggle('done', isDone);
    }
  }

  function initPayment() {
    const emailBox = document.getElementById('summaryEmail');
    const transferBox = document.getElementById('transferContent');
    const copyBtn = document.getElementById('copyTransfer');
    if (!emailBox || !transferBox) return;

    const orderId = getQueryParam('order_id');
    const cached = readLastOrder();
    const current = cached && cached.order_id === orderId ? cached : null;

    function render(order) {
      if (!order) return;
      emailBox.textContent = order.email || 'Không xác định';
      transferBox.textContent = order.transfer_content || ('NAP ' + order.order_id);
      saveLastOrder(order);
      updatePaymentTimeline(order);
    }

    if (!orderId) {
      emailBox.textContent = 'Chưa có đơn hàng';
      transferBox.textContent = 'Quay lại trang trước để tạo đơn trước.';
      return;
    }

    if (current) {
      render(current);
    }

    copyBtn && copyBtn.addEventListener('click', async function () {
      try {
        await navigator.clipboard.writeText(transferBox.textContent.trim());
        showToast('Đã sao chép nội dung chuyển khoản');
      } catch (error) {
        showToast('Không thể sao chép, hãy copy thủ công');
      }
    });

    async function poll() {
      try {
        const data = await checkOrder(orderId);
        if (!data.ok || !data.order) {
          throw new Error(data.message || 'Không đọc được trạng thái đơn.');
        }
        render(data.order);
        if (['paid', 'processing', 'completed'].includes(data.order.status)) {
          setTimeout(function () {
            window.location.href = 'success.html?order_id=' + encodeURIComponent(data.order.order_id);
          }, 800);
          return;
        }
      } catch (error) {
        console.error(error);
      }
      setTimeout(poll, Math.max(2500, Number(state.config.status_poll_interval_ms || 5000)));
    }

    poll();
  }

  function drawConfetti() {
    const canvas = document.getElementById('confettiCanvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const particles = [];
    const colors = ['#68d8ff', '#8a7dff', '#38d39f', '#ffc46b', '#ff6e8a'];

    function resize() {
      canvas.width = window.innerWidth;
      canvas.height = window.innerHeight;
    }

    function seed() {
      particles.length = 0;
      const total = Math.max(90, Math.floor(window.innerWidth / 9));
      for (let i = 0; i < total; i += 1) {
        particles.push({
          x: Math.random() * canvas.width,
          y: -Math.random() * canvas.height,
          size: 4 + Math.random() * 8,
          speedY: 1.2 + Math.random() * 3,
          speedX: -1 + Math.random() * 2,
          angle: Math.random() * Math.PI,
          spin: -0.08 + Math.random() * 0.16,
          color: colors[Math.floor(Math.random() * colors.length)],
        });
      }
    }

    function frame() {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      particles.forEach((p) => {
        p.y += p.speedY;
        p.x += p.speedX;
        p.angle += p.spin;
        if (p.y > canvas.height + 20) {
          p.y = -20;
          p.x = Math.random() * canvas.width;
        }
        ctx.save();
        ctx.translate(p.x, p.y);
        ctx.rotate(p.angle);
        ctx.fillStyle = p.color;
        ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.65);
        ctx.restore();
      });
      requestAnimationFrame(frame);
    }

    resize();
    seed();
    frame();
    window.addEventListener('resize', function () {
      resize();
      seed();
    });
  }

  async function initSuccess() {
    const emailEl = document.getElementById('doneEmail');
    const orderEl = document.getElementById('doneOrderId');
    const queryOrderId = getQueryParam('order_id');
    let order = readLastOrder();

    if ((!order || order.order_id !== queryOrderId) && queryOrderId) {
      try {
        const data = await checkOrder(queryOrderId);
        if (data.ok && data.order) {
          order = data.order;
          saveLastOrder(order);
        }
      } catch (error) {
        console.error(error);
      }
    }

    if (emailEl) emailEl.textContent = (order && order.email) || 'Không xác định';
    if (orderEl) orderEl.textContent = (order && order.order_id) || queryOrderId || 'Không xác định';
    drawConfetti();
  }

  async function boot() {
    createSparkles();
    window.addEventListener('resize', createSparkles);
    await fetchPublicConfig();
    applyPublicConfig();

    const page = document.body.getAttribute('data-page');
    if (page === 'landing') initLanding();
    if (page === 'payment') initPayment();
    if (page === 'success') initSuccess();
  }

  boot();
})();
