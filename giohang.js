let cart = JSON.parse(localStorage.getItem("cart")) || [];

function renderCartDropdown() {
  const dropdown = document.getElementById("cart-dropdown");
  if (!dropdown) return;

  if (cart.length === 0) {
    dropdown.innerHTML = `<p class="text-gray-500">Giỏ hàng trống</p>`;
    return;
  }

  dropdown.innerHTML = `
    <ul class="divide-y divide-gray-200 max-h-60 overflow-y-auto mb-2">
      ${cart.map(item => `
        <li class="py-2 flex justify-between items-center">
          <span>${item.name} (Size: ${item.size}, x${item.quantity})</span>
          <span class="text-red-500">${(item.price * item.quantity).toLocaleString()} đ</span>
        </li>
      `).join("")}
    </ul>
    <div class="text-right font-semibold">
      Tổng: <span class="text-red-600">${cart.reduce((sum, item) => sum + item.price * item.quantity, 0).toLocaleString()} đ</span>
    </div>
    <a href="cart-view.php" class="block text-center bg-blue-600 text-white py-2 mt-2 rounded hover:bg-blue-700">Xem giỏ hàng</a>
  `;
}

function addToCart(product) {
  const existingItem = cart.find(item => item.id === product.id && item.size === product.size);
  if (existingItem) {
    existingItem.quantity += product.quantity;
  } else {
    cart.push(product);
  }
  localStorage.setItem("cart", JSON.stringify(cart));
  renderCartDropdown();
  alert(`Đã thêm "${product.name}" (Size: ${product.size}, x${product.quantity}) vào giỏ hàng!`);
}

document.addEventListener("DOMContentLoaded", () => {
  renderCartDropdown();

  const btn = document.getElementById("cartButton");
  const dropdown = document.getElementById("cart-dropdown");

  btn?.addEventListener("click", () => {
    dropdown.classList.toggle("hidden");
  });

  window.addEventListener("click", function (e) {
    if (!btn.contains(e.target) && !dropdown.contains(e.target)) {
      dropdown.classList.add("hidden");
    }
  });
});