/**
 * Customer Enquiry Module
 */

const ENQUIRY_API = '/api/customer-enquiry.php';

let allEnquiries = [];
let currentTypeFilter = 'all';

function escHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

function formatDateTime(value) {
    if (!value) return '-';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value);
    return d.toLocaleString('en-GB', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

async function loadEnquiries() {
    const list = document.getElementById('enquiriesList');
    list.innerHTML = '<div style="text-align:center;padding:40px 0;color:var(--gray-500);">Loading enquiries...</div>';

    if (!(window.LOGGED_IN || false)) {
        list.innerHTML = '<div style="text-align:center;padding:40px 0;color:var(--gray-500);">Please sign in to view your enquiries.</div>';
        document.getElementById('enquiriesCount').textContent = 'Sign in required';
        return;
    }

    try {
        const res = await fetch(ENQUIRY_API + '?action=list-my-enquiries');
        const data = await res.json();

        if (!data.success) {
            list.innerHTML = '<div style="text-align:center;padding:40px 0;color:var(--danger);">' + escHtml(data.message || 'Failed to load enquiries') + '</div>';
            return;
        }

        allEnquiries = data.enquiries || [];
        renderEnquiries();
    } catch (err) {
        list.innerHTML = '<div style="text-align:center;padding:40px 0;color:var(--danger);">Network error.</div>';
    }
}

function renderEnquiries() {
    const list = document.getElementById('enquiriesList');
    const rows = allEnquiries.filter(e => currentTypeFilter === 'all' || e.enquiry_type === currentTypeFilter);

    document.getElementById('enquiriesCount').textContent = 'Showing ' + rows.length + ' enquiry(s)';

    if (rows.length === 0) {
        list.innerHTML = '<div style="text-align:center;padding:50px 0;color:var(--gray-500);">No enquiries found.</div>';
        return;
    }

    list.innerHTML = rows.map(e => {
        const typeLabel = e.enquiry_type === 'trip' ? 'Trip Enquiry' : 'General Enquiry';
        const statusColor = e.status === 'replied' ? 'var(--success)' : 'var(--warning)';
        const statusText = e.status === 'replied' ? 'Replied' : 'Open';
        const canDelete = e.status === 'open' && !e.reply_id;

        return '<div style="background:#fff;border:1px solid var(--gray-200);border-radius:12px;padding:16px;margin-bottom:12px;">'
            + '<div style="display:flex;align-items:center;gap:8px;justify-content:space-between;flex-wrap:wrap;">'
            + '  <div style="display:flex;gap:8px;align-items:center;">'
            + '    <span style="padding:2px 8px;border-radius:999px;background:var(--primary-50);color:var(--primary);font-size:0.75rem;font-weight:700;">' + escHtml(typeLabel) + '</span>'
            + '    <span style="padding:2px 8px;border-radius:999px;background:color-mix(in srgb, ' + statusColor + ' 20%, white);color:' + statusColor + ';font-size:0.75rem;font-weight:700;">' + escHtml(statusText) + '</span>'
            + '  </div>'
            + '  <span style="font-size:0.78rem;color:var(--gray-500);">' + escHtml(formatDateTime(e.created_at)) + '</span>'
            + '</div>'
            + '<p style="margin:10px 0 0;color:var(--gray-700);white-space:pre-line;">' + escHtml(e.content || '') + '</p>'
            + (e.image_src ? '<div style="margin-top:10px;"><img src="' + escHtml(e.image_src) + '" alt="enquiry" style="max-width:220px;border-radius:8px;"></div>' : '')
            + '<div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end;">'
            + '  <button class="btn btn-outline btn-sm" onclick="openEnquiryDetail(\'' + escHtml(e.id) + '\')">View Detail</button>'
            + (canDelete ? '  <button class="btn btn-danger btn-sm" onclick="deleteEnquiry(\'' + escHtml(e.id) + '\')">Delete</button>' : '')
            + '</div>'
            + '</div>';
    }).join('');
}

function filterEnquiries(type, btn) {
    currentTypeFilter = type;
    document.querySelectorAll('.enquiry-filter-btn').forEach(b => {
        b.classList.remove('active');
        b.className = b.className.replace('btn-primary', 'btn-outline');
    });
    btn.classList.add('active');
    btn.className = btn.className.replace('btn-outline', 'btn-primary');
    renderEnquiries();
}

function openEnquiryModal() {
    if (!(window.LOGGED_IN || false)) {
        showAuthModal('login');
        return;
    }
    document.getElementById('enquiryType').value = 'trip';
    document.getElementById('enquiryContent').value = '';
    document.getElementById('enquiryImage').value = '';
    document.getElementById('enquiryImagePreviewWrap').style.display = 'none';
    document.getElementById('enquiryModal').classList.add('open');
}

function previewEnquiryImage(input) {
    const file = input.files && input.files[0] ? input.files[0] : null;
    if (!file) {
        document.getElementById('enquiryImagePreviewWrap').style.display = 'none';
        return;
    }
    const reader = new FileReader();
    reader.onload = function (e) {
        document.getElementById('enquiryImagePreview').src = e.target.result;
        document.getElementById('enquiryImagePreviewWrap').style.display = 'block';
    };
    reader.readAsDataURL(file);
}

async function submitEnquiry() {
    const enquiryType = document.getElementById('enquiryType').value;
    const content = document.getElementById('enquiryContent').value.trim();
    const image = document.getElementById('enquiryImage').files[0] || null;

    if (!content) {
        showToast('Please enter enquiry content.', 'warning');
        return;
    }

    const btn = document.getElementById('submitEnquiryBtn');
    btn.disabled = true;
    btn.textContent = 'Submitting...';

    try {
        const formData = new FormData();
        formData.append('action', 'create-enquiry');
        formData.append('enquiry_type', enquiryType);
        formData.append('content', content);
        if (image) {
            formData.append('image', image);
        }

        const res = await fetch(ENQUIRY_API, {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            closeModal('enquiryModal');
            showToast('Enquiry submitted successfully.', 'success');
            loadEnquiries();
        } else {
            showToast(data.message || 'Failed to submit enquiry.', 'error');
        }
    } catch (err) {
        showToast('Network error.', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Submit Enquiry';
    }
}

async function deleteEnquiry(enquiryId) {
    if (!confirm('Delete this enquiry?')) return;

    try {
        const res = await fetch(ENQUIRY_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete-enquiry', enquiry_id: enquiryId })
        });
        const data = await res.json();
        if (data.success) {
            showToast('Enquiry deleted.', 'success');
            loadEnquiries();
        } else {
            showToast(data.message || 'Failed to delete enquiry.', 'error');
        }
    } catch (err) {
        showToast('Network error.', 'error');
    }
}

async function openEnquiryDetail(enquiryId) {
    const body = document.getElementById('enquiryDetailBody');
    body.innerHTML = '<div style="text-align:center;padding:20px;color:var(--gray-500);">Loading...</div>';
    document.getElementById('enquiryDetailModal').classList.add('open');

    try {
        const res = await fetch(ENQUIRY_API + '?action=get-enquiry-detail&enquiry_id=' + encodeURIComponent(enquiryId));
        const data = await res.json();
        if (!data.success) {
            body.innerHTML = '<div style="padding:20px;color:var(--danger);">' + escHtml(data.message || 'Failed to load detail.') + '</div>';
            return;
        }

        const e = data.enquiry;
        const typeLabel = e.enquiry_type === 'trip' ? 'Trip Enquiry' : 'General Enquiry';
        body.innerHTML = ''
            + '<div style="border:1px solid var(--gray-200);border-radius:10px;padding:14px;margin-bottom:14px;background:#fff;">'
            + '<div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;">'
            + '  <strong>' + escHtml(typeLabel) + '</strong>'
            + '  <span style="color:var(--gray-500);font-size:0.8rem;">' + escHtml(formatDateTime(e.created_at)) + '</span>'
            + '</div>'
            + '<p style="margin-top:10px;white-space:pre-line;color:var(--gray-700);">' + escHtml(e.content || '') + '</p>'
            + (e.image_src ? '<div style="margin-top:10px;"><img src="' + escHtml(e.image_src) + '" style="max-width:240px;border-radius:8px;" alt="enquiry image"></div>' : '')
            + '</div>';

        if (e.reply_id) {
            body.innerHTML += ''
                + '<div style="border:1px solid var(--gray-200);border-radius:10px;padding:14px;background:#f8fafc;">'
                + '<div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;">'
                + '  <strong>Call Center Reply</strong>'
                + '  <span style="color:var(--gray-500);font-size:0.8rem;">' + escHtml(formatDateTime(e.reply_created_at)) + '</span>'
                + '</div>'
                + '<div style="font-size:0.85rem;color:var(--gray-500);margin-top:4px;">By ' + escHtml(e.staff_name || 'Call Center Staff') + '</div>'
                + '<p style="margin-top:10px;white-space:pre-line;color:var(--gray-700);">' + escHtml(e.reply_content || '') + '</p>'
                + (e.reply_image_src ? '<div style="margin-top:10px;"><img src="' + escHtml(e.reply_image_src) + '" style="max-width:240px;border-radius:8px;" alt="reply image"></div>' : '')
                + '</div>';
        } else {
            body.innerHTML += '<div style="color:var(--warning);font-weight:600;">This enquiry is still waiting for staff response.</div>';
        }
    } catch (err) {
        body.innerHTML = '<div style="padding:20px;color:var(--danger);">Network error.</div>';
    }
}

window.filterEnquiries = filterEnquiries;
window.openEnquiryModal = openEnquiryModal;
window.previewEnquiryImage = previewEnquiryImage;
window.submitEnquiry = submitEnquiry;
window.deleteEnquiry = deleteEnquiry;
window.openEnquiryDetail = openEnquiryDetail;

document.addEventListener('DOMContentLoaded', loadEnquiries);
