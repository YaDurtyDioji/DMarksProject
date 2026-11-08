document.addEventListener('DOMContentLoaded', function() {
    console.log('barewithtea boutique portal initialized.');

    const serviceSelect = document.getElementById('serviceSelect');
    const totalPriceDisplay = document.getElementById('totalPriceDisplay');
    const cartCountDisplay = document.getElementById('cartCount');
    const priceTableBody = document.getElementById('priceTableBody');
    const promotionsContainer = document.getElementById('promotionsContainer');
    const promotionsSection = document.getElementById('promotionsSection');

    // 1. Fetch Treatments & Populate Table + Multiselect
    // Using a mocked response to simulate backend data until API is wired up.
    const mockTreatments = [
        { id: 1, name: "Full Leg Laser", category: "Laser", duration: "45 mins", price: 650.00 },
        { id: 2, name: "Underarms Laser", category: "Laser", duration: "20 mins", price: 250.00 },
        { id: 3, name: "Lash Lift & Tint", category: "Eyelashes", duration: "45 mins", price: 350.00 },
        { id: 4, name: "Classic Extensions", category: "Eyelashes", duration: "90 mins", price: 450.00 }
    ];

    // 2. Fetch Active Promotions
    const mockPromotions = [
        {
            id: 'promo-1',
            title: "Lash + Underarm Combo",
            description: "Combine our popular Lash Lift & Tint with Diode Laser Underarms.",
            price: 450.00,
            original_price: 600.00,
            image_url: "https://images.unsplash.com/photo-1512496015851-a1dc8a477852?auto=format&fit=crop&w=400&q=80",
            code: "LASH-LASER-COMBO"
        }
    ];

    function renderTreatments(treatments) {
        priceTableBody.innerHTML = '';
        serviceSelect.innerHTML = '';

        treatments.forEach(item => {
            // Add to Table
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="ps-4 fw-semibold">${item.name}</td>
                <td><span class="badge bg-secondary rounded-0">${item.category}</span></td>
                <td class="text-muted small"><i class="fa-regular fa-clock me-1"></i>${item.duration}</td>
                <td class="fw-bold">R ${item.price.toFixed(2)}</td>
                <td class="text-end pe-4">
                    <button type="button" class="btn btn-sm btn-outline-dark rounded-0 book-btn" data-id="${item.id}" data-name="${item.name}" data-price="${item.price}">
                        <i class="fa-solid fa-plus me-1"></i> Book
                    </button>
                </td>
            `;
            priceTableBody.appendChild(tr);

            // Add to Multi-Select Dropdown
            const option = document.createElement('option');
            option.value = item.id;
            option.dataset.price = item.price;
            option.textContent = `${item.name} - R ${item.price.toFixed(2)}`;
            serviceSelect.appendChild(option);
        });
        
        attachBookButtonListeners();
    }

    function renderPromotions(promos) {
        if (!promos || promos.length === 0) {
            promotionsSection.classList.add('d-none');
            return;
        }
        
        promotionsSection.classList.remove('d-none');
        promotionsContainer.innerHTML = '';

        promos.forEach(promo => {
            // Add combo option to the Multi-Select Dropdown as well
            const option = document.createElement('option');
            option.value = promo.id;
            option.dataset.price = promo.price;
            option.textContent = `[COMBO] ${promo.title} - R ${promo.price.toFixed(2)}`;
            option.classList.add('fw-bold', 'text-success');
            serviceSelect.appendChild(option);

            // Build Combo Card
            const imgHtml = promo.image_url ? `<img src="${promo.image_url}" class="card-img-top promo-img" alt="${promo.title}">` : '';
            
            const card = document.createElement('div');
            card.className = 'col-md-6 col-lg-5';
            card.innerHTML = `
                <div class="card h-100 border-0 shadow-sm rounded-0">
                    ${imgHtml}
                    <div class="card-body p-4 text-center">
                        <span class="badge bg-dark rounded-0 text-uppercase mb-2">Combo Offer</span>
                        <h4 class="fw-bold">${promo.title}</h4>
                        <p class="text-muted small">${promo.description}</p>
                        <div class="mb-3">
                            <span class="text-decoration-line-through text-muted me-2 small">R ${promo.original_price.toFixed(2)}</span>
                            <h3 class="fw-bold text-success d-inline">R ${promo.price.toFixed(2)}</h3>
                        </div>
                        <button type="button" class="btn btn-dark w-100 rounded-0 text-uppercase book-btn" data-id="${promo.id}" data-name="${promo.title}" data-price="${promo.price}">
                            <i class="fa-solid fa-plus me-1"></i> Add Combo to Wishlist
                        </button>
                    </div>
                </div>
            `;
            promotionsContainer.appendChild(card);
        });

        attachBookButtonListeners();
    }

    // Attach listeners to all "Book (+)" buttons across Table and Promos
    function attachBookButtonListeners() {
        const bookBtns = document.querySelectorAll('.book-btn');
        bookBtns.forEach(btn => {
            // Remove existing listener to prevent duplicates
            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);
            
            newBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const itemId = this.getAttribute('data-id');
                
                // Select the option in the multiselect
                const optionToSelect = serviceSelect.querySelector(`option[value="${itemId}"]`);
                if (optionToSelect) {
                    optionToSelect.selected = true;
                    // Trigger change event to update totals
                    const event = new Event('change');
                    serviceSelect.dispatchEvent(event);
                    
                    // Optional visual feedback
                    this.innerHTML = '<i class="fa-solid fa-check"></i> Added';
                    this.classList.remove('btn-outline-dark', 'btn-dark');
                    this.classList.add('btn-success', 'text-white');
                    setTimeout(() => {
                        this.innerHTML = '<i class="fa-solid fa-plus me-1"></i> Book';
                        this.classList.remove('btn-success', 'text-white');
                        this.classList.add(this.closest('#promotionsContainer') ? 'btn-dark' : 'btn-outline-dark');
                    }, 1500);

                    // Smooth scroll to form
                    document.getElementById('booking').scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    }

    // Calculate Total Price and update cart count when selection changes
    serviceSelect.addEventListener('change', function() {
        let total = 0;
        let count = 0;
        Array.from(this.selectedOptions).forEach(option => {
            total += parseFloat(option.getAttribute('data-price') || 0);
            count++;
        });
        
        totalPriceDisplay.textContent = `R ${total.toFixed(2)}`;
        cartCountDisplay.textContent = count;
    });

    // Simulate API calls
    setTimeout(() => {
        renderTreatments(mockTreatments);
        renderPromotions(mockPromotions);
    }, 500); // 500ms delay to simulate network

});
