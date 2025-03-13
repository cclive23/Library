// Get the cover modal
const coverModal = document.getElementById("coverModal");
// Get the request modal
const requestModal = document.getElementById("requestModal");

// Get the buttons that open the modals
const coverButtons = document.getElementsByClassName("show-cover");
const requestButtons = document.getElementsByClassName("show-request");

// Get the <span> elements that close the modals
const closeButtons = document.getElementsByClassName("close");

// Get the close button for cover modal
const closeModalButton = document.getElementById("close-modal");

// For cover modal
for (let i = 0; i < coverButtons.length; i++) {
    coverButtons[i].onclick = function() {
        const title = this.getAttribute("data-title");
        const coverImage = this.getAttribute("data-cover");
        
        document.getElementById("modal-title").textContent = title;
        document.getElementById("book-cover").src = coverImage;
        
        coverModal.style.display = "block";
    }
}

// For request modal
for (let i = 0; i < requestButtons.length; i++) {
    requestButtons[i].onclick = function() {
        const title = this.getAttribute("data-title");
        const bookId = this.getAttribute("data-id");
        
        document.getElementById("request-title").textContent = "Request: " + title;
        document.getElementById("request-book-id").value = bookId;
        
        // Set initial dates
        updateDates(7); // Default to 7 days
        
        requestModal.style.display = "block";
    }
}

// When the user clicks on <span> (x), close the modals
for (let i = 0; i < closeButtons.length; i++) {
    closeButtons[i].onclick = function() {
        coverModal.style.display = "none";
        requestModal.style.display = "none";
    }
}

// When the user clicks on the close button, close the cover modal
if (closeModalButton) {
    closeModalButton.onclick = function() {
        coverModal.style.display = "none";
    }
}

// When the user clicks anywhere outside of the modals, close them
window.onclick = function(event) {
    if (event.target == coverModal) {
        coverModal.style.display = "none";
    }
    if (event.target == requestModal) {
        requestModal.style.display = "none";
    }
}

// Handle the duration slider
const durationSlider = document.getElementById("duration-slider");
const durationDays = document.getElementById("duration-days");

if (durationSlider) {
    durationSlider.oninput = function() {
        const days = parseInt(this.value);
        durationDays.textContent = days;
        updateDates(days);
    }
}

// Function to update the dates based on selected duration
function updateDates(days) {
    const borrowDate = new Date();
    const returnDate = new Date();
    returnDate.setDate(returnDate.getDate() + days);
    
    document.getElementById("borrow-date").textContent = formatDate(borrowDate);
    document.getElementById("return-date").textContent = formatDate(returnDate);
}

// Helper function to format dates nicely
function formatDate(date) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return date.toLocaleDateString(undefined, options);
}

// Initialize dates on page load
document.addEventListener("DOMContentLoaded", function() {
    if (document.getElementById("borrow-date")) {
        updateDates(7); // Default to 7 days
    }
});

// Add this to your admin.js file

// Make sure the tab functionality includes the new requests tab
function openTab(tabName) {
    // Hide all tab content
    var tabContents = document.getElementsByClassName("tab-content");
    for (var i = 0; i < tabContents.length; i++) {
        tabContents[i].style.display = "none";
    }
    
    // Remove active class from all tab links
    var tabLinks = document.getElementsByClassName("tab-link");
    for (var i = 0; i < tabLinks.length; i++) {
        tabLinks[i].classList.remove("active");
    }
    
    // Show the selected tab and add active class to the tab link
    document.getElementById(tabName + "-tab").style.display = "block";
    
    // Find the clicked tab link and add active class
    var tabLinks = document.getElementsByClassName("tab-link");
    for (var i = 0; i < tabLinks.length; i++) {
        if (tabLinks[i].getAttribute("onclick").includes(tabName)) {
            tabLinks[i].classList.add("active");
        }
    }
}

// Initialize to show first tab on page load (you can modify this if needed)
document.addEventListener("DOMContentLoaded", function() {
    // Get all tab links
    var tabLinks = document.getElementsByClassName("tab-link");
    
    // Open the first tab by default
    if (tabLinks.length > 0) {
        var firstTabName = tabLinks[0].getAttribute("onclick")
            .replace("openTab('", "")
            .replace("')", "");
        openTab(firstTabName);
    }
});