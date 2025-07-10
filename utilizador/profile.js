document.addEventListener('DOMContentLoaded', function() {
    // Tab functionality
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Get the data-tab attribute value
            const tabId = button.getAttribute('data-tab');
            
            // Remove active class from all buttons and content
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Add active class to current button and content
            button.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        });
    });

    // Edit profile button functionality
    const editProfileBtn = document.querySelector('.edit-profile-btn');
    if (editProfileBtn) {
        editProfileBtn.addEventListener('click', () => {
            // Show a modal or toggle edit mode
            toggleEditMode();
        });
    }

    // Section edit buttons
    const editSectionBtns = document.querySelectorAll('.edit-section-btn');
    editSectionBtns.forEach(button => {
        button.addEventListener('click', function() {
            const section = this.closest('.profile-section');
            toggleSectionEditMode(section);
        });
    });

    // Edit avatar button
    const editAvatarBtn = document.querySelector('.edit-avatar-btn');
    if (editAvatarBtn) {
        editAvatarBtn.addEventListener('click', () => {
            // Trigger file upload
            const fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.accept = 'image/*';
            fileInput.onchange = handleAvatarUpload;
            fileInput.click();
        });
    }

    // Edit cover photo button
    const editCoverBtn = document.querySelector('.edit-cover-btn');
    if (editCoverBtn) {
        editCoverBtn.addEventListener('click', () => {
            // Trigger file upload
            const fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.accept = 'image/*';
            fileInput.onchange = handleCoverUpload;
            fileInput.click();
        });
    }

    // Order detail buttons
    const orderDetailBtns = document.querySelectorAll('.order-details-btn');
    orderDetailBtns.forEach(button => {
        button.addEventListener('click', function() {
            const orderId = this.closest('.order-item').querySelector('.order-id').textContent;
            showOrderDetails(orderId);
        });
    });

    // Remove favorite buttons
    const removeFavoriteBtns = document.querySelectorAll('.remove-favorite-btn');
    removeFavoriteBtns.forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const favoriteItem = this.closest('.favorite-item');
            removeFavoriteItem(favoriteItem);
        });
    });

    // Share profile button
    const shareProfileBtn = document.querySelector('.share-profile-btn');
    if (shareProfileBtn) {
        shareProfileBtn.addEventListener('click', () => {
            shareProfile();
        });
    }

    // Add to cart buttons
    const addToCartBtns = document.querySelectorAll('.add-to-cart-btn');
    addToCartBtns.forEach(button => {
        button.addEventListener('click', function() {
            const productName = this.closest('.favorite-item').querySelector('h3').textContent;
            addToCart(productName);
        });
    });

    // Track order buttons
    const trackOrderBtns = document.querySelectorAll('.track-order-btn');
    trackOrderBtns.forEach(button => {
        button.addEventListener('click', function() {
            const orderId = this.closest('.order-item').querySelector('.order-id').textContent;
            trackOrder(orderId);
        });
    });

    // Add smooth hover effect to achievement items
    const achievementItems = document.querySelectorAll('.achievement-item');
    achievementItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });

    // Header toggle function for dropdown (from the existing code)
    window.toggle = function() {
        const dropdownList = document.querySelector('.profile-dropdown-list');
        dropdownList.classList.toggle('active');
    }
});

// Function to toggle edit mode for the entire profile
function toggleEditMode() {
    // Create modal or change state to edit mode
    const modal = document.createElement('div');
    modal.className = 'edit-profile-modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2>Editar Perfil</h2>
                <button class="close-modal-btn">&times;</button>
            </div>
            <div class="modal-body">
                <form id="edit-profile-form">
                    <div class="form-group">
                        <label for="name">Nome Completo</label>
                        <input type="text" id="name" value="João Carlos Silva">
                    </div>
                    <div class="form-group">
                        <label for="username">Nome de Usuário</label>
                        <input type="text" id="username" value="joaosilva">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" value="joao.silva@email.com">
                    </div>
                    <div class="form-group">
                        <label for="bio">Bio</label>
                        <textarea id="bio">Designer gráfico e entusiasta de tecnologia. Apaixonado por criar experiências visualmente envolventes.</textarea>
                    </div>
                    <div class="form-group">
                        <label for="phone">Telefone</label>
                        <input type="tel" id="phone" value="+351 912 345 678">
                    </div>
                    <div class="form-group">
                        <label for="location">Localização</label>
                        <input type="text" id="location" value="Lisboa, Portugal">
                    </div>
                    <div class="form-actions">
                        <button type="button" class="cancel-btn">Cancelar</button>
                        <button type="submit" class="save-btn">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    
    // Add event listeners to modal buttons
    const closeBtn = modal.querySelector('.close-modal-btn');
    const cancelBtn = modal.querySelector('.cancel-btn');
    const form = modal.querySelector('#edit-profile-form');
    
    closeBtn.addEventListener('click', () => {
        document.body.removeChild(modal);
    });
    
    cancelBtn.addEventListener('click', () => {
        document.body.removeChild(modal);
    });
    
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        
        // Here you would handle form submission, like sending data to a server
        
        // For demo, just update the profile info
        const name = document.querySelector('.profile-name');
        const username = document.querySelector('.profile-username');
        const bio = document.querySelector('.profile-bio');
        
        if (name) name.textContent = form.querySelector('#name').value;
        if (username) username.textContent = `@${form.querySelector('#username').value}`;
        if (bio) bio.textContent = form.querySelector('#bio').value;
        
        // Update personal info section
        const infoValues = document.querySelectorAll('.info-value');
        infoValues.forEach(info => {
            if (info.previousElementSibling.textContent === 'Nome Completo') {
                info.textContent = form.querySelector('#name').value;
            } else if (info.previousElementSibling.textContent === 'Email') {
                info.textContent = form.querySelector('#email').value;
            } else if (info.previousElementSibling.textContent === 'Telefone') {
                info.textContent = form.querySelector('#phone').value;
            } else if (info.previousElementSibling.textContent === 'Localização') {
                info.textContent = form.querySelector('#location').value;
            }
        });
        
        document.body.removeChild(modal);
        
        // Show success notification
        showNotification('Perfil atualizado com sucesso!', 'success');
    });

    // Add modal styles if not already in stylesheet
    if (!document.getElementById('modal-styles')) {
        const modalStyles = document.createElement('style');
        modalStyles.id = 'modal-styles';
        modalStyles.textContent = `
            .edit-profile-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
            }
            
            .modal-content {
                background-color: white;
                border-radius: 8px;
                width: 90%;
                max-width: 600px;
                max-height: 90vh;
                overflow-y: auto;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            }
            
            .modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 16px 24px;
                border-bottom: 1px solid #e0e0e0;
            }
            
            .modal-header h2 {
                margin: 0;
                font-size: 1.5rem;
            }
            
            .close-modal-btn {
                background: none;
                border: none;
                font-size: 1.5rem;
                cursor: pointer;
                color: #555;
            }
            
            .modal-body {
                padding: 24px;
            }
            
            .form-group {
                margin-bottom: 16px;
            }
            
            .form-group label {
                display: block;
                margin-bottom: 8px;
                font-weight: 500;
            }
            
            .form-group input, .form-group textarea {
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #e0e0e0;
                border-radius: 4px;
                font-size: 1rem;
            }
            
            .form-group textarea {
                min-height: 100px;
                resize: vertical;
            }
            
            .form-actions {
                display: flex;
                justify-content: flex-end;
                gap: 12px;
                margin-top: 24px;
            }
            
            .cancel-btn, .save-btn {
                padding: 8px 16px;
                border-radius: 4px;
                font-weight: 500;
                cursor: pointer;
            }
            
            .cancel-btn {
                background-color: transparent;
                border: 1px solid #e0e0e0;
                color: #555;
            }
            
            .save-btn {
                background-color: #3a86ff;
                border: none;
                color: white;
            }
            
            .notification {
                position: fixed;
                bottom: 20px;
                right: 20px;
                padding: 12px 24px;
                border-radius: 4px;
                color: white;
                opacity: 0;
                transform: translateY(20px);
                transition: opacity 0.3s, transform 0.3s;
                z-index: 1000;
            }
            
            .notification.success {
                background-color: #38b000;
            }
            
            .notification.error {
                background-color: #e63946;
            }
            
            .notification.show {
                opacity: 1;
                transform: translateY(0);
            }
        `;
        document.head.appendChild(modalStyles);
    }
}

// Function to toggle edit mode for a specific section
function toggleSectionEditMode(section) {
    const sectionTitle = section.querySelector('h2').textContent;
    const infoItems = section.querySelectorAll('.info-item');
    
    if (sectionTitle === 'Informação Pessoal' && infoItems.length > 0) {
        // Create inline editing for personal info
        infoItems.forEach(item => {
            const label = item.querySelector('.info-label').textContent;
            const value = item.querySelector('.info-value').textContent;
            
            item.querySelector('.info-value').innerHTML = `
                <input type="text" class="edit-info-input" value="${value}" data-original="${value}">
            `;
        });
        
        // Add save/cancel buttons
        const actionButtons = document.createElement('div');
        actionButtons.className = 'section-edit-actions';
        actionButtons.innerHTML = `
            <button class="cancel-section-btn">Cancelar</button>
            <button class="save-section-btn">Salvar</button>
        `;
        section.appendChild(actionButtons);
        
        // Change edit button to close button
        const editButton = section.querySelector('.edit-section-btn');
        editButton.innerHTML = `<i class="fas fa-times"></i>`;
        editButton.setAttribute('data-editing', 'true');
        
        // Add event listeners
        const cancelBtn = section.querySelector('.cancel-section-btn');
        const saveBtn = section.querySelector('.save-section-btn');
        
        cancelBtn.addEventListener('click', () => {
            // Restore original values and remove edit mode
            infoItems.forEach(item => {
                const originalValue = item.querySelector('.edit-info-input').getAttribute('data-original');
                item.querySelector('.info-value').textContent = originalValue;
            });
            
            section.removeChild(actionButtons);
            editButton.innerHTML = `<i class="fas fa-pen"></i>`;
            editButton.removeAttribute('data-editing');
        });
        
        saveBtn.addEventListener('click', () => {
            // Save new values
            infoItems.forEach(item => {
                const newValue = item.querySelector('.edit-info-input').value;
                item.querySelector('.info-value').textContent = newValue;
            });
            
            section.removeChild(actionButtons);
            editButton.innerHTML = `<i class="fas fa-pen"></i>`;
            editButton.removeAttribute('data-editing');
            
            // Show success notification
            showNotification('Informação atualizada com sucesso!', 'success');
        });
    } else if (sectionTitle === 'Redes Sociais') {
        // For social media, could implement a different editing UI
        showNotification('Edição de redes sociais não implementada nesta versão.', 'info');
    }
}

// Function to handle avatar upload
function handleAvatarUpload(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const avatarImg = document.querySelector('.profile-avatar img');
            if (avatarImg) {
                avatarImg.src = e.target.result;
                showNotification('Foto de perfil atualizada!', 'success');
            }
        };
        reader.readAsDataURL(file);
    }
}

// Function to handle cover photo upload
function handleCoverUpload(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const coverPhoto = document.querySelector('.cover-photo');
            if (coverPhoto) {
                coverPhoto.style.backgroundImage = `url(${e.target.result})`;
                coverPhoto.style.backgroundSize = 'cover';
                coverPhoto.style.backgroundPosition = 'center';
                showNotification('Foto de capa atualizada!', 'success');
            }
        };
        reader.readAsDataURL(file);
    }
}

// Function to show order details
function showOrderDetails(orderId) {
    // This would typically fetch the details from a server
    // For demo purposes, we'll just show a notification
    showNotification(`Detalhes do pedido ${orderId} seriam mostrados aqui`, 'info');
}

// Function to remove favorite item with animation
function removeFavoriteItem(item) {
    item.style.transition = 'all 0.3s ease';
    item.style.transform = 'scale(0.8)';
    item.style.opacity = '0';
    
    setTimeout(() => {
        item.parentNode.removeChild(item);
        showNotification('Item removido dos favoritos!', 'success');
    }, 300);
}

// Function to share profile
function shareProfile() {
    // This would typically open a share dialog
    const shareData = {
        title: 'Perfil de João Silva',
        text: 'Confira o perfil de João Silva',
        url: window.location.href,
    };
    
    if (navigator.share && navigator.canShare(shareData)) {
        navigator.share(shareData)
            .then(() => showNotification('Perfil compartilhado com sucesso!', 'success'))
            .catch(error => showNotification('Erro ao compartilhar', 'error'));
    } else {
        // Fallback for browsers that don't support the Web Share API
        showNotification('Link do perfil copiado para a área de transferência!', 'success');
    }
}

// Function to add item to cart
function addToCart(productName) {
    // This would typically add the item to a cart in local storage or on a server
    showNotification(`${productName} adicionado ao carrinho!`, 'success');
}

// Function to track order
function trackOrder(orderId) {
    // This would typically open a tracking page or modal
    showNotification(`Informações de rastreio para ${orderId} seriam mostradas aqui`, 'info');
}

// Function to show notification
function showNotification(message, type) {
    // Remove any existing notifications
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        document.body.removeChild(existingNotification);
    }
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Hide and remove notification after a delay
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                document.body.removeChild(notification);
            }
        }, 300);
    }, 4000);
}