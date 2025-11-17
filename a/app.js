// Utilities
const $ = (q,doc=document)=>doc.querySelector(q);
const $$ = (q,doc=document)=>Array.from(doc.querySelectorAll(q));
const fmt = n => `${n} RON`;

// Year in footer
$('#y') && ($('#y').textContent = new Date().getFullYear());

// FILTERS
$$('.chip').forEach(chip=>{
  chip.addEventListener('click', ()=>{
    $$('.chip').forEach(c=>c.classList.remove('active'));
    chip.classList.add('active');
    const f = chip.dataset.filter;
    $$('.product').forEach(p=>{
      p.style.display = (f==='all' || p.dataset.cat===f) ? '' : 'none';
    });
  });
});

// QUICK VIEW
const modal = $('#quickModal');
const qm = {
  open: (id) => {
    // only one real product (hand-balance) in demo
    $('#qmImg').src = 'assets/hand_holder.png';
    $('#qmTitle').textContent = 'Hand Balance';
    $('#qmDesc').textContent = 'Suport sculptural low-poly, perfect pentru sticlă de vin.';
    $('#qmPrice').textContent = fmt(149);
    $('#qmLink').href = `product.html?id=${id}`;
    $('#qmAdd').onclick = () => addToCart({id, name:'Hand Balance', price:149, img:'assets/hand_holder.png'});
    modal.showModal();
  },
  close: ()=> modal.close()
};
$('#closeQ')?.addEventListener('click', qm.close);
$$('.quick').forEach(btn=>btn.addEventListener('click', ()=> qm.open(btn.dataset.id)));

// CART
const cartEl = $('#cart');
const backdrop = $('#cartBackdrop');
const openCart = ()=>{ cartEl.classList.add('open'); backdrop.classList.add('show'); renderCart(); };
const closeCart = ()=>{ cartEl.classList.remove('open'); backdrop.classList.remove('show'); };

$('#openCart')?.addEventListener('click', openCart);
$('#closeCart')?.addEventListener('click', closeCart);
backdrop?.addEventListener('click', closeCart);

let CART = JSON.parse(localStorage.getItem('FORMIFY_CART') || '[]');

function saveCart(){ localStorage.setItem('FORMIFY_CART', JSON.stringify(CART)); updateCount(); }
function updateCount(){ const c = CART.reduce((s,i)=>s+i.qty,0); $$('#cartCount').forEach(el=>el.textContent=c); }
function addToCart(item){
  const existing = CART.find(i=>i.id===item.id);
  if(existing){ existing.qty += item.qty || 1; }
  else CART.push({...item, qty: item.qty || 1});
  saveCart(); renderCart(); openCart();
}
function removeFromCart(id){ CART = CART.filter(i=>i.id!==id); saveCart(); renderCart(); }
function changeQty(id, d){
  const it = CART.find(i=>i.id===id); if(!it) return;
  it.qty = Math.max(1, it.qty + d); saveCart(); renderCart();
}
function total(){ return CART.reduce((s,i)=>s + i.price*i.qty, 0); }

function renderCart(){
  const box = $('#cartItems'); if(!box) return;
  if(CART.length===0){ box.innerHTML = `<p class="muted">Coșul tău este gol.</p>`; $('#cartTotal').textContent=fmt(0); return; }
  box.innerHTML = CART.map(i=>`
    <div class="cart-item">
      <img src="${i.img}" alt="">
      <div>
        <strong>${i.name}</strong>
        <div class="muted">${fmt(i.price)} · x${i.qty}</div>
      </div>
      <div style="display:flex; gap:6px;">
        <button class="q" onclick="changeQty('${i.id}', -1)">−</button>
        <button class="q" onclick="changeQty('${i.id}', 1)">+</button>
        <button class="q" onclick="removeFromCart('${i.id}')">×</button>
      </div>
    </div>
  `).join('');
  $('#cartTotal').textContent = fmt(total());
}
updateCount(); renderCart();

// ADD TO CART buttons (all pages)
$$('.add-to-cart').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const {id, name, price, img} = btn.dataset;
    let qty = 1;
    // on product page, read qty field if exists
    const q = $('#qty'); if(q) qty = Math.max(1, +q.value||1);
    addToCart({id, name, price:+price, img, qty});
  });
});
