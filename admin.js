
// Open specific tab
function openTab(tabName) {
    // Hide all tab content
    var tabContents = document.getElementsByClassName("tab-content");
    for (var i = 0; i < tabContents.length; i++) {
        tabContents[i].style.display = "none";
    }
    
    // Show the selected tab content
    document.getElementById(tabName + "-tab").style.display = "block";
    
    // Update active tab links
    var tabLinks = document.getElementsByClassName("tab-link");
    for (var i = 0; i < tabLinks.length; i++) {
        tabLinks[i].classList.remove("active");
    }
    
    event.currentTarget.classList.add("active");
}

// Open report tab
function openReportTab(tabName) {
    // Hide all report content
    var reportContents = document.getElementsByClassName("report-content");
    for (var i = 0; i < reportContents.length; i++) {
        reportContents[i].classList.remove("active");
    }
    
    // Show the selected report content
    document.getElementById(tabName + "-tab").classList.add("active");
    
    // Update active tab
    var tabs = document.getElementsByClassName("tab");
    for (var i = 0; i < tabs.length; i++) {
        tabs[i].classList.remove("active");
    }
    
    event.currentTarget.classList.add("active");
}

// Open modal
function openModal(modalId) {
    document.getElementById(modalId).style.display = "block";
}

// Close modal
function closeModal(modalId) {
    document.getElementById(modalId).style.display = "none";
}

function openUpdateModal(bookId, title, author, category, isbn, year, coverImage) {
    // Set values in the form
    document.getElementById('update_book_id').value = bookId;
    document.getElementById('update_title').value = title;
    document.getElementById('update_author').value = author;
    document.getElementById('update_category').value = category;
    document.getElementById('update_isbn').value = isbn || '';
    document.getElementById('update_published_year').value = year || '';
    
    // Handle cover preview
    var preview = document.getElementById('updateCoverPreview');
    if (coverImage) {
        preview.src = coverImage;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
    
    // Show the modal
    document.getElementById('updateModal').style.display = 'block';
}

function previewUpdateImage(input) {
    var preview = document.getElementById('updateCoverPreview');
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Open update user limit modal
function openUpdateLimitModal(userId, currentLimit) {
    document.getElementById("update_user_id").value = userId;
    document.getElementById("new_limit").value = currentLimit;
    openModal('updateLimitModal');
}

// Preview book cover image
function previewImage(input) {
    var preview = document.getElementById('coverPreview');
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.style.display = 'none';
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    var modals = document.getElementsByClassName("modal");
    for (var i = 0; i < modals.length; i++) {
        if (event.target == modals[i]) {
            modals[i].style.display = "none";
        }
    }
}

// Open the default tab on page load
document.addEventListener('DOMContentLoaded', function() {
    openTab('books');
});



