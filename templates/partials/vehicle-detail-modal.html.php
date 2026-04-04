<!-- ===== VEHICLE DETAIL MODAL ===== -->
<div class="modal-overlay" id="carDetailModal">
    <div class="modal" style="max-width:800px;max-height:92vh;overflow-y:auto;padding:0;">
        <div class="detail-gallery" id="detailGallery">
            <div class="detail-gallery-main" id="detailMainImage">
                <img src="/resources/images/logo/logo.png" alt="Vehicle" style="width:100%;height:100%;object-fit:cover;">
            </div>
            <div class="detail-gallery-thumbs" id="detailThumbs"></div>
            <button class="modal-close" onclick="closeModal('carDetailModal')" style="position:absolute;top:12px;right:12px;z-index:5;background:rgba(0,0,0,0.5);color:white;border:none;width:36px;height:36px;border-radius:50%;font-size:1.1rem;cursor:pointer;">✕</button>
        </div>

        <div style="padding:28px 32px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
                <div>
                    <h2 class="detail-car-title" id="detailTitle" style="font-size:1.5rem;font-weight:800;color:var(--gray-900);margin-bottom:4px;"></h2>
                    <p class="detail-car-sub" id="detailSub" style="font-size:0.875rem;color:var(--gray-500);"></p>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:1.2rem;font-weight:800;color:var(--primary);" id="detailPrice"></div>
                    <div style="font-size:0.8rem;color:var(--gray-500);" id="detailAvailabilityLabel">Availability</div>
                </div>
            </div>

            <div id="detailRating" style="display:flex;align-items:center;gap:8px;margin-bottom:20px;"></div>

            <div id="detailQualitySection" style="padding:14px;background:var(--primary-50);border-radius:var(--radius-md);margin-bottom:24px;"></div>

            <div id="detailCategoryInsightSection" style="padding:14px;background:#f7faf9;border:1px solid var(--gray-200);border-radius:var(--radius-md);margin-bottom:20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
                    <h4 style="font-size:0.85rem;font-weight:700;color:var(--gray-700);text-transform:uppercase;letter-spacing:0.05em;margin:0;">Category Profile</h4>
                    <span id="detailCategoryBadge" class="detail-category-badge">Sedan</span>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <span class="detail-insight-chip">🧳 Luggage: <strong id="detailLuggageCapacityLbs">N/A</strong></span>
                    <span class="detail-insight-chip">✨ Amenity Focus: <strong id="detailAmenityFocus">N/A</strong></span>
                </div>
            </div>

            <div id="detailComparisonSection" style="margin-bottom:24px;">
                <h4 style="font-size:0.85rem;font-weight:700;color:var(--gray-600);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;">Category Comparison</h4>
                <div id="detailCategoryComparison" class="detail-comparison-wrap"></div>
            </div>

            <div class="detail-specs-grid" id="detailSpecs" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px;"></div>

            <div id="detailFeaturesSection" style="margin-bottom:24px;display:none;">
                <h4 style="font-size:0.85rem;font-weight:700;color:var(--gray-600);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;">Amenities</h4>
                <div id="detailFeatures" style="display:flex;flex-wrap:wrap;gap:8px;"></div>
            </div>

            <div id="detailLocationSection" style="margin-bottom:24px;display:none;">
                <h4 style="font-size:0.85rem;font-weight:700;color:var(--gray-600);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;">Location</h4>
                <div id="detailLocation" style="font-size:0.938rem;color:var(--gray-700);"></div>
            </div>

            <div id="detailPriceSection" style="padding:16px;background:var(--primary-50);border-radius:var(--radius-md);margin-bottom:24px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-size:0.875rem;color:var(--gray-600);">Daily Rate</span>
                    <span style="font-weight:700;color:var(--gray-800);" id="detailDailyRate"></span>
                </div>
                <div id="detailWeeklyRow" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;display:none;">
                    <span style="font-size:0.875rem;color:var(--gray-600);">Weekly Rate</span>
                    <span style="font-weight:700;color:var(--gray-800);" id="detailWeeklyRate"></span>
                </div>
                <div id="detailMonthlyRow" style="display:flex;justify-content:space-between;align-items:center;display:none;">
                    <span style="font-size:0.875rem;color:var(--gray-600);">Monthly Rate</span>
                    <span style="font-weight:700;color:var(--gray-800);" id="detailMonthlyRate"></span>
                </div>
            </div>

            <div style="display:flex;gap:12px;">
                <button class="btn btn-outline" style="flex:1;" onclick="closeModal('carDetailModal')">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
    .detail-gallery { position: relative; width: 100%; background: var(--gray-900); }
    .detail-gallery-main {
        width: 100%; height: 320px; display: flex; align-items: center; justify-content: center;
        overflow: hidden;
    }
    .detail-gallery-main img { width: 100%; height: 100%; object-fit: cover; }
    .detail-gallery-thumbs {
        display: flex; gap: 6px; padding: 8px 12px; background: rgba(0,0,0,0.6);
        overflow-x: auto; position: absolute; bottom: 0; left: 0; right: 0;
    }
    .detail-gallery-thumbs:empty { display: none; }
    .detail-thumb {
        width: 56px; height: 40px; border-radius: 6px; overflow: hidden; cursor: pointer;
        border: 2px solid transparent; flex-shrink: 0; opacity: 0.6; transition: all 0.2s;
    }
    .detail-thumb:hover, .detail-thumb.active { opacity: 1; border-color: white; }
    .detail-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .detail-spec-item {
        padding: 14px 10px; background: var(--gray-50); border-radius: var(--radius-md); text-align: center;
    }
    .detail-spec-icon { font-size: 1.25rem; margin-bottom: 4px; }
    .detail-spec-label { font-size: 0.7rem; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 2px; }
    .detail-spec-value { font-size: 0.875rem; font-weight: 700; color: var(--gray-800); }
    .detail-feature-tag {
        padding: 6px 14px; border-radius: var(--radius-full); font-size: 0.8rem;
        font-weight: 500; background: var(--gray-100); color: var(--gray-700); border: 1px solid var(--gray-200);
    }
    .quality-pill {
        display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px;
        border-radius: 999px; background: white; border: 1px solid var(--primary-200);
        font-size: 0.75rem; font-weight: 700; color: var(--primary);
    }
    .detail-category-badge {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 10px; border-radius: 999px;
        font-size: 0.75rem; font-weight: 800;
        background: #e8f4f1; color: #1f4b43; border: 1px solid #cde5df;
        text-transform: uppercase;
    }
    .detail-insight-chip {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 10px; border-radius: 999px;
        background: white; border: 1px solid var(--gray-200);
        font-size: 0.78rem; color: var(--gray-700); font-weight: 600;
    }
    .detail-comparison-wrap {
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-md);
        overflow-x: auto;
        background: white;
    }
    .detail-comparison-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 520px;
    }
    .detail-comparison-table th,
    .detail-comparison-table td {
        padding: 10px;
        border-bottom: 1px solid var(--gray-100);
        text-align: left;
        font-size: 0.8rem;
    }
    .detail-comparison-table th {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--gray-600);
        background: #f8faf9;
    }
    .detail-comparison-table tr.active-row {
        background: #ebf7f4;
    }
    .detail-comparison-table tr.active-row td {
        font-weight: 700;
        color: #1f4b43;
    }
    @media (max-width: 640px) {
        .detail-gallery-main { height: 220px; }
        #detailSpecs { grid-template-columns: repeat(2, 1fr) !important; }
    }
</style>
