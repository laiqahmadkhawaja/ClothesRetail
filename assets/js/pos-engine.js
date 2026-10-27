// assets/js/pos-engine.js - Full Clothing POS & Cart Engine with Mobile Support
let cart = [];
let currentCategory = 0;
let taxPercent = 0; // configured from php
let currentPricingTier = 'retail'; // 'retail' or 'wholesale'

function setGlobalPricingTier(tier) {
    currentPricingTier = (tier === 'wholesale') ? 'wholesale' : 'retail';
    const rRadio = document.getElementById("tierRetail");
    const wRadio = document.getElementById("tierWholesale");
    if (rRadio && wRadio) {
        if (currentPricingTier === 'wholesale') {
            wRadio.checked = true;
        } else {
            rRadio.checked = true;
        }
    }

    // Update prices of existing items in cart
    cart.forEach(item => {
        let baseRate = (currentPricingTier === 'wholesale' && item.wholesale_price > 0) ? item.wholesale_price : item.selling_price;
        if (item.unit_type === 'suit' && item.meters_per_suit > 0) {
            item.unit_price = baseRate * item.meters_per_suit;
        } else {
            item.unit_price = baseRate;
        }
        item.pricing_tier = currentPricingTier;
        item.total_price = (item.qty * item.unit_price) - item.discount;
    });

    renderCart();
    showToast(`Switched to ${currentPricingTier === 'wholesale' ? 'Wholesale (تھوک)' : 'Retail (پرچون)'} Rates`, 'info');
}
window.setGlobalPricingTier = setGlobalPricingTier;

// Audio feedback for barcode scans
const audioCtx = (window.AudioContext || window.webkitAudioContext) ? new (window.AudioContext || window.webkitAudioContext)() : null;
function playBeep(freq = 880, duration = 0.08) {
    if (!audioCtx) return;
    try {
        if (audioCtx.state === 'suspended') audioCtx.resume();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.frequency.value = freq;
        gain.gain.value = 0.15;
        osc.start();
        osc.stop(audioCtx.currentTime + duration);
    } catch (e) {}
}

document.addEventListener("DOMContentLoaded", function() {
    loadProducts();
    updateHeldCount();

    // 1. Search Barcode & Products
    const searchInput = document.getElementById("posSearchInput");
    let searchTimeout = null;

    searchInput.addEventListener("input", function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            const q = searchInput.value.trim();
            if (q.length >= 2 || q.length === 0) {
                loadProducts(q, currentCategory);
            }
        }, 300);
    });

    searchInput.addEventListener("keydown", function(e) {
        if (e.key === "Enter") {
            e.preventDefault();
            const q = searchInput.value.trim();
            if (q) {
                handleBarcodeEnter(q);
            }
        }
    });

    document.getElementById("clearSearchBtn").addEventListener("click", function() {
        searchInput.value = "";
        loadProducts("", currentCategory);
        searchInput.focus();
    });

    // 2. Category Filter Buttons
    document.querySelectorAll(".category-filter-btn").forEach(btn => {
        btn.addEventListener("click", function() {
            document.querySelectorAll(".category-filter-btn").forEach(b => {
                b.classList.remove("btn-primary", "active");
                b.classList.add("btn-outline-secondary");
            });
            this.classList.remove("btn-outline-secondary");
            this.classList.add("btn-primary", "active");

            currentCategory = parseInt(this.dataset.id) || 0;
            loadProducts(searchInput.value.trim(), currentCategory);
        });
    });

    // 3. Cart Discount Input
    document.getElementById("cartBillDiscount").addEventListener("input", calculateTotals);

    // 4. Clear Cart
    document.getElementById("clearCartBtn").addEventListener("click", function() {
        if (cart.length > 0 && confirm("Are you sure you want to clear the entire cart?")) {
            cart = [];
            renderCart();
            showToast("Cart cleared", "secondary");
        }
    });

    // 5. Open Dedicated Checkout View
    const openPayModalBtn = document.getElementById("openPayModalBtn");
    if (openPayModalBtn) {
        openPayModalBtn.addEventListener("click", openCheckoutView);
    }
    const backToCartBtn = document.getElementById("backToCartBtn");
    if (backToCartBtn) {
        backToCartBtn.addEventListener("click", closeCheckoutView);
    }
    const cancelCheckoutBtn = document.getElementById("cancelCheckoutBtn");
    if (cancelCheckoutBtn) {
        cancelCheckoutBtn.addEventListener("click", closeCheckoutView);
    }

    // 6. Payment Method Change
    document.getElementById("checkoutMethodSelect").addEventListener("change", function() {
        const method = this.value;
        const splitSec = document.getElementById("splitPaymentSection");
        const singleSec = document.getElementById("singleCashSection");

        if (method === "split") {
            splitSec.classList.remove("d-none");
            singleSec.classList.add("d-none");
        } else {
            splitSec.classList.add("d-none");
            singleSec.classList.remove("d-none");
        }
        recalcPaymentDifference();
    });

    // 7. Cash Received Inputs
    document.getElementById("cashReceivedInput").addEventListener("input", recalcPaymentDifference);
    document.getElementById("cashReceivedInput").addEventListener("keydown", function(e) {
        if (e.key === "Enter") {
            e.preventDefault();
            submitCheckout('thermal');
        }
    });
    ['splitCash', 'splitJazzCash', 'splitDigital', 'splitCard'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener("input", recalcPaymentDifference);
    });

    // Quick cash buttons
    document.querySelectorAll(".quick-cash-btn").forEach(btn => {
        btn.addEventListener("click", function() {
            const val = this.dataset.val;
            const net = getCartNetTotal();
            const input = document.getElementById("cashReceivedInput");
            if (val === "exact") {
                input.value = net;
            } else {
                input.value = parseFloat(val);
            }
            recalcPaymentDifference();
        });
    });

    // 8. Confirm Checkout
    document.getElementById("confirmCheckoutThermalBtn").addEventListener("click", () => submitCheckout('thermal'));
    document.getElementById("confirmCheckoutA4Btn").addEventListener("click", () => submitCheckout('a4'));

    // 9. Hold Sale
    document.getElementById("holdSaleBtn").addEventListener("click", holdCurrentSale);

    // 10. Quick Add Customer Form
    document.getElementById("quickCustomerForm").addEventListener("submit", function(e) {
        e.preventDefault();
        saveQuickCustomer();
    });

    // 11. Open Camera Barcode Scanner Button
    const openScannerBtn = document.getElementById("openScannerBtn");
    if (openScannerBtn) {
        openScannerBtn.addEventListener("click", openBarcodeScanner);
    }

    // 12. Global Keyboard Shortcuts: F2 (search), F3 (camera scanner), F4 (hold), F6/F7 (rates), F8 (pay), Esc
    document.addEventListener("keydown", function(e) {
        if (e.key === "F2") {
            e.preventDefault();
            searchInput.focus();
            searchInput.select();
        } else if (e.key === "F3") {
            e.preventDefault();
            const sc = document.getElementById("posCameraScannerContainer");
            if (sc && sc.classList.contains("scanner-active")) {
                closeBarcodeScanner();
            } else {
                openBarcodeScanner();
            }
        } else if (e.key === "F4") {
            e.preventDefault();
            holdCurrentSale();
        } else if (e.key === "F6") {
            e.preventDefault();
            setGlobalPricingTier('retail');
        } else if (e.key === "F7") {
            e.preventDefault();
            setGlobalPricingTier('wholesale');
        } else if (e.key === "F8") {
            e.preventDefault();
            const checkoutView = document.getElementById("posCheckoutView");
            if (checkoutView && checkoutView.style.display !== "none" && !checkoutView.classList.contains("d-none")) {
                submitCheckout('thermal');
            } else {
                openCheckoutView();
            }
        } else if (e.key === "Escape") {
            const sc = document.getElementById("posCameraScannerContainer");
            if (sc && sc.classList.contains("scanner-active")) {
                e.preventDefault();
                closeBarcodeScanner();
                return;
            }
            const checkoutView = document.getElementById("posCheckoutView");
            if (checkoutView && checkoutView.style.display !== "none" && !checkoutView.classList.contains("d-none")) {
                e.preventDefault();
                closeCheckoutView();
                return;
            }
        }
    });

    // Initialize Camera Scanner Event Bindings
    initBarcodeScannerListeners();

    // Set initial mobile view
    if (window.innerWidth < 992) {
        switchMobileView('products');
    }
});

// Mobile View Switcher (Garments Catalogue vs Cart & Bill)
function switchMobileView(view) {
    if (window.innerWidth >= 992) return; // desktop layout shows both side-by-side

    const prodCol = document.getElementById("posProductsColumn");
    const cartCol = document.getElementById("posCartColumn");
    const tabProd = document.getElementById("tabMobileProducts");
    const tabCart = document.getElementById("tabMobileCart");

    if (view === 'products') {
        prodCol.classList.remove("d-none");
        cartCol.classList.add("d-none");
        tabProd.classList.add("active");
        tabCart.classList.remove("active");
    } else {
        prodCol.classList.add("d-none");
        cartCol.classList.remove("d-none");
        tabProd.classList.remove("active");
        tabCart.classList.add("active");
    }
}

// Load products catalogue into grid
function loadProducts(query = '', category_id = 0) {
    const container = document.getElementById("posProductsContainer");
    const loader = document.getElementById("productGridLoading");
    loader.classList.remove("d-none");

    fetch(`ajax/pos_search.php?q=${encodeURIComponent(query)}&category_id=${category_id}`)
        .then(res => res.json())
        .then(data => {
            loader.classList.add("d-none");
            container.innerHTML = "";

            if (!data.products || data.products.length === 0) {
                container.innerHTML = `
                    <div class="col-12 text-center py-5 text-muted">
                        <i class="bi bi-search display-6 text-secondary mb-2 d-block"></i>
                        No garments match "${query}".<br>
                        <small>Try a different keyword or category.</small>
                    </div>`;
                return;
            }

            data.products.forEach(p => {
                const totalStock = parseFloat(p.total_stock) || 0;
                const minPrice = parseFloat(p.min_price) || parseFloat(p.default_selling_price) || 0;
                const maxPrice = parseFloat(p.max_price) || minPrice;
                const minWholesale = parseFloat(p.min_wholesale_price) || parseFloat(p.default_wholesale_price) || 0;
                const priceLabel = (minPrice === maxPrice) ? `Rs. ${minPrice.toLocaleString()}` : `Rs. ${minPrice.toLocaleString()} - ${maxPrice.toLocaleString()}`;

                const isOutOfStock = totalStock <= 0;
                const isFabric = (p.item_type === 'fabric');
                const unitLabel = isFabric ? (p.unit_type || 'm') : 'pcs';

                const col = document.createElement("div");
                col.className = "col-6 col-sm-6 col-md-4 col-xl-3";
                col.innerHTML = `
                    <div class="pos-product-card p-2 p-md-3 ${isOutOfStock ? 'out-of-stock' : ''}" onclick="selectProductForVariants(${p.id})">
                        <div class="w-100">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 0.68rem;">${p.product_code}</span>
                                <span class="badge ${totalStock > 5 ? 'bg-success-subtle text-success' : (totalStock > 0 ? 'bg-warning-subtle text-warning' : 'bg-danger-subtle text-danger')} px-2 py-1 fw-bold" style="font-size: 0.68rem;">
                                    ${parseFloat(totalStock.toFixed(2))} ${unitLabel}
                                </span>
                            </div>
                            <div class="fw-bold text-dark mt-1" title="${p.name}" style="font-size: 0.88rem; line-height: 1.25; min-height: 2.5em; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; word-break: break-word;">
                                ${isFabric ? '🧵 ' : ''}${p.name}
                            </div>
                            <div class="text-muted text-truncate" style="font-size: 0.72rem;">${p.category_name || ''}${p.brand_name ? ' &bull; ' + p.brand_name : ''}</div>
                        </div>
                        <div class="mt-2 pt-2 border-top w-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong class="text-primary fs-6">${priceLabel}</strong>
                                ${isFabric ? `<span class="badge" style="font-size: 0.65rem; background-color: #f3e8ff; color: #6b21a8; font-weight: 600;">Thaan</span>` : '<span class="badge bg-light text-muted border" style="font-size: 0.65rem;">Garment</span>'}
                            </div>
                            ${minWholesale > 0 ? `<div class="text-success fw-semibold mt-1" style="font-size: 0.72rem;"><i class="bi bi-box-seam me-1"></i>W-Sale: Rs. ${minWholesale.toLocaleString()}</div>` : ''}
                        </div>
                    </div>
                `;
                container.appendChild(col);
            });
        })
        .catch(err => {
            loader.classList.add("d-none");
            console.error("Error loading POS products:", err);
        });
}

// Handle Barcode Direct Scan
function handleBarcodeEnter(query) {
    fetch(`ajax/pos_search.php?q=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .then(data => {
            if (data.exact_match && data.variant) {
                playBeep(980, 0.1);
                addVariantToCart(data.variant, 1);
                document.getElementById("posSearchInput").value = "";
                showToast(`Added: ${data.variant.product_name} (${data.variant.size_name}/${data.variant.color_name})`, "success");
            } else if (data.products && data.products.length === 1) {
                selectProductForVariants(data.products[0].id);
            } else {
                showToast("Barcode not found or multiple items matched.", "warning");
            }
        })
        .catch(err => console.error(err));
}

// Select Product and Open Size & Color Variant Modal
function selectProductForVariants(productId) {
    fetch(`ajax/get_product_variants.php?product_id=${productId}`)
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }

            const p = data.product;
            const isFabric = (p.item_type === 'fabric');
            const suitMeters = parseFloat(p.meters_per_suit) || 4.5;

            document.getElementById("variantModalTitle").innerHTML = `
                <i class="bi ${isFabric ? 'bi-scissors' : 'bi-tag-fill'} me-2"></i> ${p.name}
                <span class="badge bg-white text-dark ms-2 fw-normal small">${p.product_code}</span>
                ${isFabric ? `<span class="badge bg-purple text-white ms-1" style="background-color: #7c3aed;">Fabric / Thaan</span>` : ''}
            `;

            const container = document.getElementById("variantGridContainer");
            container.innerHTML = "";

            if (!data.variants || data.variants.length === 0) {
                container.innerHTML = '<div class="col-12 text-center text-muted py-3">No variants configured for this product. Use Size & Color Matrix to configure variants.</div>';
            } else {
                data.variants.forEach(v => {
                    const stock = parseFloat(v.stock_quantity) || 0;
                    const isOut = stock <= 0;
                    const sellPrice = parseFloat(v.selling_price) || 0;
                    const wholesalePrice = parseFloat(v.wholesale_price) || 0;
                    const suitSellPrice = sellPrice * suitMeters;
                    const suitWholesalePrice = wholesalePrice * suitMeters;

                    const col = document.createElement("div");
                    col.className = "col-12 col-sm-6 col-md-4";
                    col.innerHTML = `
                        <div class="card p-2 border ${isOut ? 'bg-light text-muted opacity-50' : 'border-primary-subtle shadow-sm'} h-100" style="cursor: ${isOut ? 'not-allowed' : 'pointer'}; touch-action: manipulation;" onclick='${isOut ? '' : `chooseVariantWithUnit(${JSON.stringify(v)}, ${JSON.stringify(p)}, "${isFabric ? 'meter' : 'piece'}")`}'>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <span class="size-badge me-1">${v.size_name}</span>
                                    <span class="color-dot" style="background-color: ${v.color_hex};"></span>
                                    <span class="small fw-semibold">${v.color_name}</span>
                                </div>
                                <span class="badge ${stock > 3 ? 'bg-success' : (stock > 0 ? 'bg-warning text-dark' : 'bg-danger')}">
                                    ${parseFloat(stock.toFixed(2))} ${isFabric ? (p.unit_type || 'm') : 'left'}
                                </span>
                            </div>
                            <div class="mt-2 pt-1 border-top">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="small text-muted">SKU: ${v.sku}</span>
                                    <strong class="text-primary">Rs. ${sellPrice.toLocaleString()} <small class="text-muted">/${isFabric ? (p.unit_type || 'm') : 'pc'}</small></strong>
                                </div>
                                ${wholesalePrice > 0 ? `<div class="small text-success text-end">W-Sale: Rs. ${wholesalePrice.toLocaleString()}</div>` : ''}
                            </div>
                            ${isFabric && suitMeters > 0 ? `
                                <div class="d-flex gap-1 mt-2 pt-2 border-top" onclick="event.stopPropagation()">
                                    <button class="btn btn-sm btn-outline-primary py-1 flex-fill fw-semibold" style="font-size: 0.75rem;" onclick='chooseVariantWithUnit(${JSON.stringify(v)}, ${JSON.stringify(p)}, "meter")'>
                                        + Meter (Rs. ${sellPrice})
                                    </button>
                                    <button class="btn btn-sm btn-primary py-1 flex-fill fw-semibold" style="font-size: 0.75rem;" onclick='chooseVariantWithUnit(${JSON.stringify(v)}, ${JSON.stringify(p)}, "suit")'>
                                        + Suit ${suitMeters}m (Rs. ${suitSellPrice.toLocaleString()})
                                    </button>
                                </div>
                            ` : ''}
                        </div>
                    `;
                    container.appendChild(col);
                });
            }

            const modal = new bootstrap.Modal(document.getElementById("variantSelectModal"));
            modal.show();
        })
        .catch(err => console.error(err));
}

// When a variant is picked in the modal with specific unit
function chooseVariantWithUnit(variant, product, unit = null) {
    const modalEl = document.getElementById("variantSelectModal");
    const instance = bootstrap.Modal.getInstance(modalEl);
    if (instance) instance.hide();

    playBeep(920, 0.08);
    addVariantToCart(variant, 1, unit, product);
    showToast(`Added: ${variant.size_name} / ${variant.color_name} (${unit || 'unit'})`, "success");
}

function chooseVariant(variant) {
    chooseVariantWithUnit(variant, null, null);
}

// Add Variant to Cart with Fabric & Pricing Tier support
function addVariantToCart(variant, qty = 1, unit = null, prodMeta = null) {
    const itemType = (variant.item_type || (prodMeta && prodMeta.item_type) || 'readymade');
    const unitType = unit || (variant.unit_type || (prodMeta && prodMeta.unit_type) || 'piece');
    const metersPerSuit = parseFloat(variant.meters_per_suit || (prodMeta && prodMeta.meters_per_suit) || 4.5);

    const sellPrice = parseFloat(variant.selling_price) || 0;
    const wholesalePrice = parseFloat(variant.wholesale_price) || 0;
    let baseRate = (currentPricingTier === 'wholesale' && wholesalePrice > 0) ? wholesalePrice : sellPrice;

    let finalPrice = baseRate;
    if (unitType === 'suit' && metersPerSuit > 0) {
        finalPrice = baseRate * metersPerSuit;
    }

    const availableStock = parseFloat(variant.stock_quantity) || 0;
    const existing = cart.find(item => item.variant_id === variant.id && item.unit_type === unitType && item.pricing_tier === currentPricingTier);

    if (existing) {
        existing.qty += qty;
        existing.total_price = (existing.qty * existing.unit_price) - existing.discount;
    } else {
        cart.push({
            variant_id: variant.id,
            product_id: variant.product_id,
            product_name: variant.product_name,
            size_name: variant.size_name,
            color_name: variant.color_name,
            color_hex: variant.color_hex,
            sku: variant.sku,
            barcode: variant.barcode,
            item_type: itemType,
            unit_type: unitType,
            meters_per_suit: metersPerSuit,
            selling_price: sellPrice,
            wholesale_price: wholesalePrice,
            pricing_tier: currentPricingTier,
            unit_price: finalPrice,
            cost_price: parseFloat(variant.purchase_price) || 0,
            qty: qty,
            discount: 0,
            max_stock: availableStock,
            total_price: finalPrice * qty
        });
    }

    renderCart();
}

// Render Cart Table with Generic Fabric & Tier Toggles
function renderCart() {
    const tbody = document.getElementById("cartTableBody");
    tbody.innerHTML = "";

    if (cart.length === 0) {
        tbody.innerHTML = `
            <tr id="emptyCartRow">
                <td colspan="5" class="text-center py-5 text-muted">
                    <i class="bi bi-cart3 display-6 d-block text-secondary mb-2"></i>
                    Cart is empty<br>
                    <small>Scan barcode or tap a garment/fabric card</small>
                </td>
            </tr>`;
        calculateTotals();
        return;
    }

    cart.forEach((item, index) => {
        const isFabric = (item.item_type === 'fabric');
        const tr = document.createElement("tr");
        tr.innerHTML = `
            <td>
                <div class="fw-semibold text-dark text-truncate" style="max-width: 140px;" title="${item.product_name}">
                    ${isFabric ? '🧵 ' : ''}${item.product_name}
                </div>
                <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                    <span class="size-badge">${item.size_name}</span>
                    <span class="color-dot" style="background-color: ${item.color_hex};"></span>
                    <small class="text-muted" style="font-size: 0.68rem;">${item.color_name}</small>
                    <span class="badge ${item.pricing_tier === 'wholesale' ? 'bg-success-subtle text-success border border-success' : 'bg-primary-subtle text-primary border'} px-1 py-0" style="font-size: 0.65rem; cursor: pointer;" onclick="toggleItemTier(${index})" title="Toggle Retail / Wholesale rate">
                        ${item.pricing_tier === 'wholesale' ? '📦 W-Sale' : '🛒 Retail'}
                    </span>
                </div>
            </td>
            <td class="text-center">
                <div class="d-flex flex-column align-items-center">
                    <div class="input-group input-group-sm justify-content-center" style="width: 105px;">
                        <button class="btn btn-outline-secondary touch-btn px-1 py-0" onclick="changeQty(${index}, -${isFabric ? 0.5 : 1})">-</button>
                        <input type="number" step="${isFabric ? 0.25 : 1}" class="form-control form-control-sm text-center px-1 py-0 fw-bold" value="${item.qty}" onchange="changeQtyDirect(${index}, this.value)" style="width: 48px;">
                        <button class="btn btn-outline-secondary touch-btn px-1 py-0" onclick="changeQty(${index}, ${isFabric ? 0.5 : 1})">+</button>
                    </div>
                    <div class="d-flex align-items-center gap-1 mt-1">
                        ${isFabric ? `
                            <button type="button" class="badge ${item.unit_type === 'suit' ? 'bg-primary text-white' : 'bg-light text-dark border'} border-0 p-1" style="font-size: 0.65rem; cursor: pointer;" onclick="toggleItemUnit(${index})">
                                ${item.unit_type === 'suit' ? 'Suit (' + item.meters_per_suit + 'm)' : 'Meters'}
                            </button>
                        ` : `
                            <small class="text-muted" style="font-size: 0.65rem;">${item.unit_type || 'pcs'}</small>
                        `}
                    </div>
                </div>
            </td>
            <td class="text-end">
                <input type="number" step="0.01" class="form-control form-control-sm text-end px-1 py-0" value="${item.unit_price}" onchange="changePrice(${index}, this.value)" style="width: 72px; display: inline-block;">
                <small class="d-block text-muted" style="font-size: 0.65rem;">/${item.unit_type}</small>
            </td>
            <td class="text-end fw-bold text-dark">
                Rs. ${Math.round(item.total_price).toLocaleString()}
            </td>
            <td class="text-center">
                <button class="btn btn-link text-danger p-0" onclick="removeCartItem(${index})" title="Remove item">
                    <i class="bi bi-x-circle fs-6"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    calculateTotals();
}

function toggleItemTier(index) {
    const item = cart[index];
    const newTier = (item.pricing_tier === 'wholesale') ? 'retail' : 'wholesale';
    item.pricing_tier = newTier;
    let baseRate = (newTier === 'wholesale' && item.wholesale_price > 0) ? item.wholesale_price : item.selling_price;
    if (item.unit_type === 'suit' && item.meters_per_suit > 0) {
        item.unit_price = baseRate * item.meters_per_suit;
    } else {
        item.unit_price = baseRate;
    }
    item.total_price = (item.qty * item.unit_price) - item.discount;
    renderCart();
}

function toggleItemUnit(index) {
    const item = cart[index];
    const baseRate = (item.pricing_tier === 'wholesale' && item.wholesale_price > 0) ? item.wholesale_price : item.selling_price;
    if (item.unit_type === 'suit') {
        item.unit_type = 'meter';
        item.unit_price = baseRate;
    } else {
        item.unit_type = 'suit';
        const m = item.meters_per_suit > 0 ? item.meters_per_suit : 4.5;
        item.unit_price = baseRate * m;
    }
    item.total_price = (item.qty * item.unit_price) - item.discount;
    renderCart();
}

function changeQty(index, delta) {
    const item = cart[index];
    const newQty = Math.max(0.25, parseFloat((item.qty + delta).toFixed(2)));
    if (newQty <= 0) {
        removeCartItem(index);
        return;
    }
    item.qty = newQty;
    item.total_price = (item.qty * item.unit_price) - item.discount;
    renderCart();
}

function changeQtyDirect(index, val) {
    const item = cart[index];
    let q = parseFloat(val);
    if (isNaN(q) || q <= 0) {
        removeCartItem(index);
        return;
    }
    item.qty = q;
    item.total_price = (item.qty * item.unit_price) - item.discount;
    renderCart();
}

function changePrice(index, newPrice) {
    const p = parseFloat(newPrice) || 0;
    cart[index].unit_price = p;
    cart[index].total_price = (cart[index].qty * p) - cart[index].discount;
    renderCart();
}

function removeCartItem(index) {
    cart.splice(index, 1);
    renderCart();
}

// Calculate Totals and Update Mobile Badges
function calculateTotals() {
    let subtotal = 0;
    let totalItems = 0;
    cart.forEach(item => {
        subtotal += item.total_price;
        totalItems += item.qty;
    });

    const discount = parseFloat(document.getElementById("cartBillDiscount").value) || 0;
    let net = subtotal - discount;
    if (net < 0) net = 0;

    document.getElementById("cartSubtotal").innerText = `Rs. ${subtotal.toLocaleString()}`;
    document.getElementById("cartNetTotal").innerText = `Rs. ${net.toLocaleString()}`;

    // Update Mobile Nav Badges & Sticky Bar
    const mobBadge = document.getElementById("mobileCartBadge");
    const mobTotalBadge = document.getElementById("mobileCartTotalBadge");
    const stickyBar = document.getElementById("mobileCartStickyBar");
    const stickyCount = document.getElementById("stickyItemCount");
    const stickyTotal = document.getElementById("stickyTotalVal");

    if (mobBadge) mobBadge.innerText = totalItems;
    if (mobTotalBadge) mobTotalBadge.innerText = `Rs. ${net.toLocaleString()}`;

    if (stickyBar) {
        if (totalItems > 0 && window.innerWidth < 992) {
            stickyBar.classList.remove("d-none");
            if (stickyCount) stickyCount.innerText = totalItems;
            if (stickyTotal) stickyTotal.innerText = `Rs. ${net.toLocaleString()}`;
        } else {
            stickyBar.classList.add("d-none");
        }
    }
}

function getCartNetTotal() {
    let subtotal = 0;
    cart.forEach(item => subtotal += item.total_price);
    const discount = parseFloat(document.getElementById("cartBillDiscount").value) || 0;
    let net = subtotal - discount;
    return net < 0 ? 0 : net;
}

// Open Dedicated Inline Checkout View
function openCheckoutView() {
    if (cart.length === 0) {
        showToast("Cart is empty! Add products before checkout.", "warning");
        return;
    }

    const net = getCartNetTotal();
    document.getElementById("modalNetPayable").innerText = `Rs. ${net.toLocaleString()}`;
    document.getElementById("cashReceivedInput").value = net; // default exact cash
    
    // Customer summary
    const custSelect = document.getElementById("posCustomerSelect");
    const custName = custSelect.options[custSelect.selectedIndex]?.text || "Walk-in";
    const custSummaryEl = document.getElementById("checkoutCustomerSummary");
    if (custSummaryEl) {
        custSummaryEl.innerText = `Customer: ${custName}`;
    }

    const countBadge = document.getElementById("checkoutItemCountBadge");
    if (countBadge) {
        let totalItems = cart.reduce((acc, it) => acc + (parseFloat(it.quantity) || 0), 0);
        countBadge.innerText = `${totalItems} Qty in Cart`;
    }

    recalcPaymentDifference();

    // Toggle views in posCartColumn
    const cartView = document.getElementById("posCartView");
    const checkoutView = document.getElementById("posCheckoutView");
    if (cartView && checkoutView) {
        cartView.style.setProperty("display", "none", "important");
        checkoutView.style.setProperty("display", "flex", "important");
    }

    // Auto-focus cash input
    setTimeout(() => {
        const cashInp = document.getElementById("cashReceivedInput");
        if (cashInp) {
            cashInp.focus();
            cashInp.select();
        }
    }, 100);
}

// Close Dedicated Inline Checkout View and Return to Cart
function closeCheckoutView() {
    const cartView = document.getElementById("posCartView");
    const checkoutView = document.getElementById("posCheckoutView");
    if (cartView && checkoutView) {
        checkoutView.style.setProperty("display", "none", "important");
        cartView.style.setProperty("display", "flex", "important");
    }
}

// Backward compatibility alias
function openCheckoutModal() {
    openCheckoutView();
}

// Recalculate Cash / Change / Due
function recalcPaymentDifference() {
    const net = getCartNetTotal();
    const method = document.getElementById("checkoutMethodSelect").value;
    let totalPaid = 0;

    if (method === "split") {
        const c = parseFloat(document.getElementById("splitCash").value) || 0;
        const j = parseFloat(document.getElementById("splitJazzCash").value) || 0;
        const d = parseFloat(document.getElementById("splitDigital").value) || 0;
        const cr = parseFloat(document.getElementById("splitCard").value) || 0;
        totalPaid = c + j + d + cr;
    } else {
        totalPaid = parseFloat(document.getElementById("cashReceivedInput").value) || 0;
    }

    let change = 0;
    let due = 0;

    if (totalPaid >= net) {
        change = totalPaid - net;
        due = 0;
    } else {
        change = 0;
        due = net - totalPaid;
    }

    document.getElementById("changeReturnDisplay").innerText = `Rs. ${change.toLocaleString()}`;
    document.getElementById("balanceDueDisplay").innerText = `Rs. ${due.toLocaleString()}`;

    // Warn if customer is Walk-in and due > 0
    const custId = parseInt(document.getElementById("posCustomerSelect").value) || 1;
    const alertEl = document.getElementById("customerCreditAlert");
    if (custId === 1 && due > 0) {
        alertEl.innerText = "Walk-in customers cannot have credit dues! Select a Khata customer.";
        alertEl.classList.remove("d-none");
    } else {
        alertEl.classList.add("d-none");
    }
}

// Submit Checkout
function submitCheckout(printType = 'thermal') {
    const net = getCartNetTotal();
    const custId = parseInt(document.getElementById("posCustomerSelect").value) || 1;
    const method = document.getElementById("checkoutMethodSelect").value;
    const discount = parseFloat(document.getElementById("cartBillDiscount").value) || 0;
    const notes = document.getElementById("checkoutNotes").value.trim();

    let paidAmount = 0;
    let splitPayments = [];

    if (method === "split") {
        const c = parseFloat(document.getElementById("splitCash").value) || 0;
        const j = parseFloat(document.getElementById("splitJazzCash").value) || 0;
        const d = parseFloat(document.getElementById("splitDigital").value) || 0;
        const cr = parseFloat(document.getElementById("splitCard").value) || 0;
        paidAmount = c + j + d + cr;

        if (c > 0) splitPayments.push({ method: 'cash', amount: c });
        if (j > 0) splitPayments.push({ method: 'jazzcash', amount: j });
        if (d > 0) splitPayments.push({ method: 'easypaisa', amount: d });
        if (cr > 0) splitPayments.push({ method: 'card', amount: cr });
    } else {
        paidAmount = parseFloat(document.getElementById("cashReceivedInput").value) || 0;
    }

    if (custId === 1 && paidAmount < net) {
        alert("Walk-in customer must pay full amount. For partial or credit Khata sales, please select a registered customer.");
        return;
    }

    const payload = {
        customer_id: custId,
        items: cart,
        bill_discount: discount,
        payment_method: method,
        split_payments: splitPayments,
        paid_amount: paidAmount,
        notes: notes,
        pricing_tier: currentPricingTier
    };

    fetch('ajax/pos_checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            alert("Checkout Error: " + data.error);
            return;
        }

        closeCheckoutView();
        cart = [];
        renderCart();
        document.getElementById("cartBillDiscount").value = 0;
        document.getElementById("checkoutNotes").value = "";

        showToast(`Invoice #${data.invoice_number} created successfully!`, "success");

        // Open Printable receipt / invoice in popup
        const printUrl = (printType === 'thermal') 
            ? `print_receipt.php?id=${data.sale_id}&autoprint=1`
            : `print_invoice.php?id=${data.sale_id}&autoprint=1`;
        
        window.open(printUrl, '_blank', 'width=600,height=750,scrollbars=yes');

        // Reload products in background to refresh stock counts
        loadProducts(document.getElementById("posSearchInput").value.trim(), currentCategory);

        // Switch back to garments view on mobile
        if (window.innerWidth < 992) {
            switchMobileView('products');
        }
    })
    .catch(err => {
        alert("Transaction failed: " + err.message);
    });
}

// Hold Current Sale
function holdCurrentSale() {
    if (cart.length === 0) {
        showToast("Cart is empty. Nothing to hold.", "warning");
        return;
    }

    const custId = parseInt(document.getElementById("posCustomerSelect").value) || 1;
    const discount = parseFloat(document.getElementById("cartBillDiscount").value) || 0;
    const net = getCartNetTotal();

    const payload = {
        customer_id: custId,
        items: cart,
        bill_discount: discount,
        subtotal: net + discount,
        net_total: net,
        notes: prompt("Enter a note for this held sale:", "Counter Sale Hold") || "Held Sale"
    };

    fetch('ajax/pos_hold.php?action=hold', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(`Sale held as #${data.hold_number}`, "info");
            cart = [];
            renderCart();
            updateHeldCount();
            if (window.innerWidth < 992) {
                switchMobileView('products');
            }
        } else {
            alert(data.error);
        }
    });
}

// Update Held Sales Count and Table
function updateHeldCount() {
    fetch('ajax/pos_hold.php?action=list')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const count = data.held_sales.length;
                document.getElementById("heldCount").innerText = count;

                const tbody = document.getElementById("heldSalesTableBody");
                tbody.innerHTML = "";
                if (count === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No held sales currently parked.</td></tr>';
                } else {
                    data.held_sales.forEach(s => {
                        const tr = document.createElement("tr");
                        tr.innerHTML = `
                            <td><strong>${s.invoice_number}</strong></td>
                            <td>${s.sale_date}</td>
                            <td>${s.customer_name || 'Walk-in'}</td>
                            <td>${s.item_count} items</td>
                            <td><strong>Rs. ${parseFloat(s.net_total).toLocaleString()}</strong></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-primary" onclick="resumeHeldSale(${s.id})">
                                    <i class="bi bi-arrow-clockwise"></i> Resume
                                </button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            }
        });
}

function resumeHeldSale(saleId) {
    if (cart.length > 0 && !confirm("Current cart has items. Resuming will overwrite current cart. Continue?")) {
        return;
    }

    fetch(`ajax/pos_hold.php?action=load&sale_id=${saleId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                cart = [];
                data.items.forEach(it => {
                    cart.push({
                        variant_id: it.variant_id,
                        product_id: it.product_id,
                        product_name: it.product_name,
                        size_name: it.size_name,
                        color_name: it.color_name,
                        color_hex: it.color_hex,
                        sku: it.sku,
                        barcode: it.barcode,
                        unit_price: parseFloat(it.unit_price),
                        cost_price: parseFloat(it.cost_price),
                        qty: parseInt(it.quantity),
                        discount: parseFloat(it.discount),
                        max_stock: parseInt(it.stock_quantity),
                        total_price: parseFloat(it.total_price)
                    });
                });

                document.getElementById("cartBillDiscount").value = data.sale.bill_discount;
                if (data.sale.customer_id) {
                    document.getElementById("posCustomerSelect").value = data.sale.customer_id;
                }

                bootstrap.Modal.getInstance(document.getElementById("heldSalesModal")).hide();
                renderCart();
                updateHeldCount();
                showToast("Held sale resumed into cart!", "success");

                if (window.innerWidth < 992) {
                    switchMobileView('cart');
                }
            } else {
                alert(data.error);
            }
        });
}

// Quick Add Customer Save
function saveQuickCustomer() {
    const name = document.getElementById("quickCustName").value.trim();
    const phone = document.getElementById("quickCustPhone").value.trim();
    const limit = document.getElementById("quickCustLimit").value;
    const addr = document.getElementById("quickCustAddress").value.trim();

    const fd = new FormData();
    fd.append('action', 'quick_add');
    fd.append('name', name);
    fd.append('phone', phone);
    fd.append('credit_limit', limit);
    fd.append('address', addr);

    fetch('customers.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const sel = document.getElementById("posCustomerSelect");
                const opt = document.createElement("option");
                opt.value = data.customer.id;
                opt.innerText = `${data.customer.name} (${data.customer.phone})`;
                opt.selected = true;
                sel.appendChild(opt);

                const panel = document.getElementById("quickCustomerPanel");
                if (panel) {
                    const bsCollapse = bootstrap.Collapse.getInstance(panel) || new bootstrap.Collapse(panel, {toggle: false});
                    bsCollapse.hide();
                }
                document.getElementById("quickCustomerForm").reset();
                showToast("Khata customer created and selected!", "success");
            } else {
                alert("Failed to create customer: " + data.error);
            }
        });
}

// ========================================================
// LIVE CAMERA BARCODE SCANNER (AUTO-ZOOM & ALIGNMENT)
// ========================================================
let scannerStream = null;
let scannerVideo = null;
let scannerCanvas = null;
let scannerCtx = null;
let scannerAnimationId = null;
let isScanningActive = false;
let nativeBarcodeDetector = null;
let zxingReader = null;
let currentZoom = 1.0;
let autoZoomEnabled = true;
let continuousScan = false;
let facingMode = 'environment';
let isTorchOn = false;
let lastDetectedBarcode = '';
let lastDetectedTime = 0;
let hardwareZoomSupported = false;
let minHardwareZoom = 1;
let maxHardwareZoom = 4;
let lastScanTimestamp = 0;

function initBarcodeScannerListeners() {
    const scannerContainer = document.getElementById("posCameraScannerContainer");
    if (!scannerContainer) return;

    scannerVideo = document.getElementById("scannerVideo");
    scannerCanvas = document.getElementById("scannerCanvas");
    if (scannerCanvas) scannerCtx = scannerCanvas.getContext("2d", { willReadFrequently: true });

    // Close button
    const closeBtn = document.getElementById("closeScannerBtn");
    if (closeBtn) {
        closeBtn.addEventListener("click", closeBarcodeScanner);
    }

    // Initialize native BarcodeDetector if supported
    if ('BarcodeDetector' in window) {
        try {
            nativeBarcodeDetector = new BarcodeDetector({
                formats: ['code_128', 'code_39', 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'qr_code']
            });
        } catch (e) {
            nativeBarcodeDetector = null;
        }
    }

    // Initialize ZXing offline fallback
    if (window.ZXing && !zxingReader) {
        try {
            zxingReader = new ZXing.BrowserMultiFormatReader();
        } catch (e) {
            zxingReader = null;
        }
    }

    // Zoom Buttons
    document.querySelectorAll(".zoom-btn").forEach(btn => {
        btn.addEventListener("click", function() {
            const z = parseFloat(this.dataset.zoom) || 1.0;
            setScannerZoom(z);
        });
    });

    // Zoom Range Slider
    const zoomSlider = document.getElementById("zoomRangeSlider");
    if (zoomSlider) {
        zoomSlider.addEventListener("input", function() {
            setScannerZoom(parseFloat(this.value));
        });
    }

    // Auto-zoom Toggle Switch
    const autoZoomSwitch = document.getElementById("autoZoomSwitch");
    if (autoZoomSwitch) {
        autoZoomSwitch.addEventListener("change", function() {
            autoZoomEnabled = this.checked;
            showToast(autoZoomEnabled ? "Auto-Zoom & Alignment: Enabled" : "Auto-Zoom: Disabled", "secondary");
        });
    }

    // Continuous Scan Toggle Switch
    const contSwitch = document.getElementById("continuousScanSwitch");
    if (contSwitch) {
        contSwitch.addEventListener("change", function() {
            continuousScan = this.checked;
        });
    }

    // Torch / Flashlight Toggle
    const torchBtn = document.getElementById("torchToggleBtn");
    if (torchBtn) {
        torchBtn.addEventListener("click", toggleTorch);
    }

    // Flip Camera (Front / Rear)
    const switchCamBtn = document.getElementById("switchCameraBtn");
    if (switchCamBtn) {
        switchCamBtn.addEventListener("click", switchCamera);
    }
}

// Open Live Camera Barcode Scanner with Smooth Animation above Cart
function openBarcodeScanner() {
    const container = document.getElementById("posCameraScannerContainer");
    if (!container) return;

    // Toggle behavior
    if (container.classList.contains("scanner-active")) {
        closeBarcodeScanner();
        return;
    }

    container.style.display = "block";
    requestAnimationFrame(() => {
        container.classList.add("scanner-active");
    });
    startScannerCamera();
}

// Close Live Camera Barcode Scanner with Collapse Animation
function closeBarcodeScanner() {
    const container = document.getElementById("posCameraScannerContainer");
    if (!container) return;

    container.classList.remove("scanner-active");
    stopScannerStream();
    setTimeout(() => {
        if (!container.classList.contains("scanner-active")) {
            container.style.display = "none";
        }
    }, 350);
}

function startScannerCamera() {
    scannerVideo = document.getElementById("scannerVideo");
    const statusBadge = document.getElementById("scannerStatusBadge");
    const reticle = document.getElementById("scannerReticle");
    if (reticle) reticle.classList.remove("aligned");
    if (statusBadge) {
        statusBadge.className = "badge bg-dark bg-opacity-75 text-white scanner-floating-badge";
        statusBadge.innerHTML = '<i class="bi bi-camera-video me-1"></i> Starting camera...';
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert("Camera access is not supported by your browser or requires HTTPS / localhost.");
        closeBarcodeScanner();
        return;
    }

    // Request camera feed with preferred environment rear camera
    const constraints = {
        video: {
            facingMode: { ideal: facingMode },
            width: { ideal: 1280 },
            height: { ideal: 720 },
            focusMode: { ideal: 'continuous' }
        },
        audio: false
    };

    navigator.mediaDevices.getUserMedia(constraints)
        .then(stream => {
            scannerStream = stream;
            scannerVideo.srcObject = stream;
            scannerVideo.setAttribute("playsinline", "true");
            scannerVideo.play();

            // Detect hardware capabilities (hardware zoom & torch)
            const track = stream.getVideoTracks()[0];
            const caps = (track && track.getCapabilities) ? track.getCapabilities() : {};

            hardwareZoomSupported = !!caps.zoom;
            if (hardwareZoomSupported) {
                minHardwareZoom = caps.zoom.min || 1;
                maxHardwareZoom = caps.zoom.max || 4;
                const slider = document.getElementById("zoomRangeSlider");
                if (slider) {
                    slider.min = minHardwareZoom;
                    slider.max = maxHardwareZoom;
                }
            }

            const torchBtn = document.getElementById("torchToggleBtn");
            if (torchBtn) {
                if (caps.torch) {
                    torchBtn.style.display = "inline-flex";
                    isTorchOn = false;
                    document.getElementById("torchLabel").innerText = "Torch On";
                } else {
                    torchBtn.style.display = "none";
                }
            }

            isScanningActive = true;
            setScannerZoom(1.0);

            if (statusBadge) {
                statusBadge.className = "badge bg-primary text-white scanner-floating-badge";
                statusBadge.innerHTML = '<i class="bi bi-upc-scan me-1"></i> Point at barcode';
            }

            // Start scan frame loop
            requestAnimationFrame(scanVideoFrame);
        })
        .catch(err => {
            console.error("Camera access error:", err);
            alert("Could not access camera: " + (err.message || err.name) + ". Please check camera permissions.");
            closeBarcodeScanner();
        });
}

function stopScannerStream() {
    isScanningActive = false;
    if (scannerAnimationId) {
        cancelAnimationFrame(scannerAnimationId);
        scannerAnimationId = null;
    }

    if (scannerStream) {
        scannerStream.getTracks().forEach(track => {
            track.stop();
        });
        scannerStream = null;
    }

    if (scannerVideo) {
        scannerVideo.srcObject = null;
        scannerVideo.style.transform = "scale(1)";
    }

    const reticle = document.getElementById("scannerReticle");
    if (reticle) reticle.classList.remove("aligned");

    const banner = document.getElementById("lastScannedBanner");
    if (banner) banner.classList.add("d-none");
}

function setScannerZoom(zoomVal) {
    currentZoom = Math.max(1.0, Math.min(4.0, Math.round(zoomVal * 10) / 10));

    // Update UI elements
    const zoomBadge = document.getElementById("scannerZoomBadge");
    if (zoomBadge) zoomBadge.innerText = `${currentZoom.toFixed(1)}x Zoom`;

    const slider = document.getElementById("zoomRangeSlider");
    if (slider) slider.value = currentZoom;

    document.querySelectorAll(".zoom-btn").forEach(btn => {
        const bz = parseFloat(btn.dataset.zoom);
        if (Math.abs(bz - currentZoom) < 0.25) {
            btn.classList.add("active");
            btn.classList.remove("btn-outline-secondary");
            btn.classList.add("btn-primary");
        } else {
            btn.classList.remove("active");
            btn.classList.add("btn-outline-secondary");
            btn.classList.remove("btn-primary");
        }
    });

    // Apply hardware camera zoom if supported
    if (hardwareZoomSupported && scannerStream) {
        const track = scannerStream.getVideoTracks()[0];
        if (track && track.applyConstraints) {
            track.applyConstraints({
                advanced: [{ zoom: Math.min(maxHardwareZoom, Math.max(minHardwareZoom, currentZoom)) }]
            }).catch(() => {});
        }
    }

    // Also apply smooth CSS digital transform zoom
    if (scannerVideo) {
        scannerVideo.style.transform = `scale(${currentZoom})`;
    }
}

function toggleTorch() {
    if (!scannerStream) return;
    const track = scannerStream.getVideoTracks()[0];
    if (track && track.applyConstraints) {
        isTorchOn = !isTorchOn;
        track.applyConstraints({ advanced: [{ torch: isTorchOn }] })
            .then(() => {
                const label = document.getElementById("torchLabel");
                if (label) label.innerText = isTorchOn ? "Torch Off" : "Torch On";
                const btn = document.getElementById("torchToggleBtn");
                if (btn) {
                    if (isTorchOn) {
                        btn.classList.add("btn-warning", "text-dark");
                        btn.classList.remove("btn-outline-secondary");
                    } else {
                        btn.classList.remove("btn-warning", "text-dark");
                        btn.classList.add("btn-outline-secondary");
                    }
                }
            })
            .catch(() => {});
    }
}

function switchCamera() {
    facingMode = (facingMode === 'environment') ? 'user' : 'environment';
    stopScannerStream();
    startScannerCamera();
}

function scanVideoFrame(now) {
    if (!isScanningActive || !scannerVideo || scannerVideo.readyState !== 4) {
        if (isScanningActive) {
            scannerAnimationId = requestAnimationFrame(scanVideoFrame);
        }
        return;
    }

    // Throttle scan rate to ~25 frames/second
    if (now - lastScanTimestamp < 40) {
        scannerAnimationId = requestAnimationFrame(scanVideoFrame);
        return;
    }
    lastScanTimestamp = now;

    // Primary: Native BarcodeDetector
    if (nativeBarcodeDetector) {
        nativeBarcodeDetector.detect(scannerVideo)
            .then(barcodes => {
                if (!isScanningActive) return;
                if (barcodes && barcodes.length > 0) {
                    const b = barcodes[0];
                    handleBarcodeMatch(b.rawValue, b.boundingBox);
                } else {
                    handleUnmatchedFrame();
                }
            })
            .catch(() => {
                fallbackZXingScan();
            })
            .finally(() => {
                if (isScanningActive) {
                    scannerAnimationId = requestAnimationFrame(scanVideoFrame);
                }
            });
    } else {
        // Fallback: ZXing scan
        fallbackZXingScan();
        if (isScanningActive) {
            scannerAnimationId = requestAnimationFrame(scanVideoFrame);
        }
    }
}

function fallbackZXingScan() {
    if (!zxingReader || !scannerCanvas || !scannerCtx || !scannerVideo) return;
    try {
        scannerCanvas.width = scannerVideo.videoWidth || 640;
        scannerCanvas.height = scannerVideo.videoHeight || 480;
        scannerCtx.drawImage(scannerVideo, 0, 0, scannerCanvas.width, scannerCanvas.height);

        zxingReader.decodeFromCanvas(scannerCanvas)
            .then(result => {
                if (result && result.getText()) {
                    handleBarcodeMatch(result.getText(), null);
                }
            })
            .catch(() => {
                handleUnmatchedFrame();
            });
    } catch (e) {}
}

function handleUnmatchedFrame() {
    const reticle = document.getElementById("scannerReticle");
    if (reticle && reticle.classList.contains("aligned")) {
        // Return to normal searching laser state smoothly
        reticle.classList.remove("aligned");
        const statusBadge = document.getElementById("scannerStatusBadge");
        if (statusBadge && statusBadge.innerText.indexOf("Captured") === -1) {
            statusBadge.className = "badge bg-primary text-white scanner-floating-badge";
            statusBadge.innerHTML = '<i class="bi bi-upc-scan me-1"></i> Scanning...';
        }
    }
}

function handleBarcodeMatch(barcodeText, boundingBox) {
    if (!barcodeText) return;
    const code = barcodeText.trim();
    if (!code) return;

    const reticle = document.getElementById("scannerReticle");
    if (reticle) reticle.classList.add("aligned");

    // Smart Auto-Zoom & Auto-Alignment Adjustment
    if (autoZoomEnabled && boundingBox && scannerVideo) {
        const vidWidth = scannerVideo.videoWidth || 1280;
        const boxWidth = boundingBox.width;
        const targetWidth = vidWidth * 0.45; // reticle target area width approx

        if (boxWidth > 0 && targetWidth > 0) {
            const ratio = boxWidth / targetWidth;
            // If barcode is too small / far away, zoom in to bring into sharp focus
            if (ratio < 0.38 && currentZoom < 3.2) {
                setScannerZoom(currentZoom + 0.3);
            } 
            // If barcode is too large / overflowing frame, zoom out
            else if (ratio > 0.88 && currentZoom > 1.2) {
                setScannerZoom(currentZoom - 0.3);
            }
        }
    }

    // Debounce to prevent reading the exact same barcode multiple times within 1.5s
    const now = Date.now();
    if (code === lastDetectedBarcode && (now - lastDetectedTime < 1500)) {
        return;
    }

    lastDetectedBarcode = code;
    lastDetectedTime = now;

    // Visual & Haptic & Audio Feedback
    playBeep(980, 0.12);
    if (navigator.vibrate) {
        try { navigator.vibrate([40, 30, 40]); } catch (e) {}
    }

    const statusBadge = document.getElementById("scannerStatusBadge");
    if (statusBadge) {
        statusBadge.className = "badge bg-success text-white scanner-floating-badge";
        statusBadge.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> Scanned: ${code}`;
    }

    // Process Barcode into Cart
    processCameraScannedBarcode(code);
}

function processCameraScannedBarcode(code) {
    fetch(`ajax/pos_search.php?q=${encodeURIComponent(code)}`)
        .then(res => res.json())
        .then(data => {
            if (data.exact_match && data.variant) {
                const v = data.variant;
                addVariantToCart(v, 1);

                // Show confirmation pill in scanner
                const banner = document.getElementById("lastScannedBanner");
                const nameEl = document.getElementById("lastScannedName");
                const varEl = document.getElementById("lastScannedVariant");
                const priceEl = document.getElementById("lastScannedPrice");

                if (banner && nameEl) {
                    nameEl.innerText = v.product_name;
                    if (varEl) varEl.innerText = `(${v.size_name || ''} / ${v.color_name || ''})`;
                    if (priceEl) priceEl.innerText = `Rs. ${parseFloat(v.selling_price).toLocaleString()}`;
                    banner.classList.remove("d-none");
                }

                showToast(`Added: ${v.product_name} (${v.size_name}/${v.color_name})`, "success");

                // Auto-close if continuous scan is disabled
                if (!continuousScan) {
                    setTimeout(() => {
                        closeBarcodeScanner();
                        // Focus on search input for next manual or hardware scan
                        const searchInput = document.getElementById("posSearchInput");
                        if (searchInput) searchInput.focus();
                    }, 450);
                }
            } else if (data.products && data.products.length === 1) {
                // If barcode matched product with multiple variants, open variant chooser
                closeBarcodeScanner();
                selectProductForVariants(data.products[0].id);
            } else {
                showToast(`Barcode "${code}" not found in garment catalog.`, "warning");
            }
        })
        .catch(err => {
            console.error("Barcode lookup error:", err);
            showToast("Lookup failed: " + err, "danger");
        });
}
