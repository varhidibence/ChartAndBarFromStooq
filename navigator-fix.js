document.addEventListener("DOMContentLoaded", function () {
  const stockBox = document.querySelector(".stock-box");
  const preHeader = document.querySelector(".pre-header");

  console.log('Put stock-box div inside pre-header');

  if (stockBox && preHeader && preHeader.parentNode) {
    // Move the stock-box *after* the pre-header
    preHeader.parentNode.insertBefore(stockBox, preHeader.nextSibling);
  }
});