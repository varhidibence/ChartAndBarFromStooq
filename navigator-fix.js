document.addEventListener("DOMContentLoaded", function () {
  const stockBox = document.querySelector(".stock-box");
  const containerDiv = document.querySelector('.pre-header .container');
 
  // Select the first <ul> inside it
  if (containerDiv) {
    const firstUl = containerDiv.querySelector('ul');

    console.log('Navigator stock-box fix');
    if (stockBox && firstUl) {
      
      // Insert before the first <ul>
      containerDiv.insertBefore(stockBox, firstUl);

      console.log('Navigator stock-box fix: Put stock-box inside pre-header inserted');
    }

    if (window.innerWidth <= 768) {
      console.log('mobile view...');
      const emailItem = containerDiv.querySelector("li .fa-envelope-o");
      if (emailItem && emailItem.parentElement) {
        console.log('Hide email from top bar at mobile view');
        emailItem.parentElement.style.display = "none";
      }
    }
  }
  else {
    console.log('No .pre-header .container div found');
  }
  
});