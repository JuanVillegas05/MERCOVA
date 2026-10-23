var e=`mercova_token`;function t(){return localStorage.getItem(e)}function n(t){localStorage.setItem(e,t)}function r(){localStorage.removeItem(e)}async function i(e,n={}){let r=t(),i={Accept:`application/json`,...n.body instanceof FormData?{}:{"Content-Type":`application/json`},...r?{Authorization:`Bearer ${r}`}:{},...n.headers||{}},a=await fetch(`/api${e}`,{...n,headers:i}),o=null;try{o=await a.json()}catch{o=null}if(!a.ok){let e=o?.message||(o?.errors?Object.values(o.errors).flat()[0]:null)||`Ocurrió un error inesperado.`,t=Error(e);throw t.status=a.status,t.errors=o?.errors||null,t}return o}var a={customer:`Cliente`,seller:`Vendedor`,admin:`Administrador`};function o(e){return a[e]||e}async function s(){if(!t())return null;try{let{user:e}=await i(`/me`);return e}catch{return r(),null}}async function c(e,t){let r=await i(`/login`,{method:`POST`,body:JSON.stringify({email:e,password:t})});return n(r.token),r.user,r.user}async function l(e){let t=await i(`/register`,{method:`POST`,body:JSON.stringify(e)});return n(t.token),t.user,t.user}async function u(){try{await i(`/logout`,{method:`POST`})}catch{}r()}async function d(){let e=await s(),t=document.querySelectorAll(`[data-auth="guest"]`),n=document.querySelectorAll(`[data-auth="user"]`),r=document.querySelectorAll(`[data-user-name]`),i=document.querySelectorAll(`[data-user-role]`);return e?(t.forEach(e=>e.classList.add(`hidden`)),n.forEach(e=>{e.classList.remove(`hidden`),e.classList.add(`flex`)}),r.forEach(t=>{t.textContent=e.name}),i.forEach(t=>{t.textContent=o(e.role)}),document.querySelectorAll(`[data-panel-link]`).forEach(t=>{t.setAttribute(`href`,e.role===`admin`?`/admin`:`/panel`)})):(t.forEach(e=>e.classList.remove(`hidden`)),n.forEach(e=>{e.classList.add(`hidden`),e.classList.remove(`flex`)})),e}function f(){document.querySelectorAll(`[data-action="logout"]`).forEach(e=>{e.addEventListener(`click`,async()=>{await u(),window.location.href=`/`})})}function p(){return t()?!0:(window.location.href=`/login?next=${encodeURIComponent(window.location.pathname)}`,!1)}async function m(){if(!p())return null;let e=await s();return!e||e.role!==`admin`?(window.location.href=`/login`,null):e}async function h(){let{data:e}=await i(`/cart`);return e}async function g(e,t=1){return i(`/cart/items`,{method:`POST`,body:JSON.stringify({product_id:e,quantity:t})})}async function _(e,t){return i(`/cart/items/${e}`,{method:`PUT`,body:JSON.stringify({quantity:t})})}async function v(e){return i(`/cart/items/${e}`,{method:`DELETE`})}async function y(){let e=document.querySelectorAll(`[data-cart-count]`);if(e.length){if(!t()){e.forEach(e=>e.classList.add(`hidden`));return}try{let t=((await h()).items||[]).reduce((e,t)=>e+t.quantity,0);e.forEach(e=>{e.textContent=t,e.classList.toggle(`hidden`,t===0)})}catch{e.forEach(e=>e.classList.add(`hidden`))}}}var b={"&":`&amp;`,"<":`&lt;`,">":`&gt;`,'"':`&quot;`,"'":`&#39;`};function x(e){return e==null?``:String(e).replace(/[&<>"']/g,e=>b[e])}var S=3e4;function C(e){let t=Math.floor((Date.now()-new Date(e).getTime())/1e3);if(t<60)return`hace un momento`;let n=Math.floor(t/60);if(n<60)return`hace ${n} min`;let r=Math.floor(n/60);return r<24?`hace ${r} h`:`hace ${Math.floor(r/24)} d`}function ee(e){let t=e.read_at?``:`<span class="w-2 h-2 rounded-full bg-emerald-500 mt-1.5 shrink-0"></span>`;return`
        <button type="button" data-notification-id="${e.id}"
            class="w-full text-left px-4 py-3 flex gap-2 hover:bg-slate-50 border-b border-slate-100 last:border-0 ${e.read_at?`opacity-60`:``}">
            ${t}
            <span class="flex-1 min-w-0">
                <span class="block text-sm font-semibold text-slate-800">${x(e.title)}</span>
                <span class="block text-xs text-slate-500 truncate">${x(e.message||``)}</span>
                <span class="block text-[11px] text-slate-400 mt-0.5">${C(e.created_at)}</span>
            </span>
        </button>
    `}function w(e=`/admin/notifications`){let t=document.getElementById(`notif-bell-btn`),n=document.getElementById(`notif-badge`),r=document.getElementById(`notif-dropdown`),a=document.getElementById(`notif-list`),o=document.getElementById(`notif-mark-all`);if(!t||!r||!a)return;async function s(){try{let{data:t,unread_count:r}=await i(e),o=t.data||[];r>0?(n.textContent=r>9?`9+`:r,n.classList.remove(`hidden`)):n.classList.add(`hidden`),a.innerHTML=o.length?o.map(ee).join(``):`<p class="text-sm text-slate-400 text-center py-6">Sin notificaciones todavía.</p>`}catch{}}t.addEventListener(`click`,()=>{r.classList.toggle(`hidden`)}),document.addEventListener(`click`,e=>{!r.contains(e.target)&&!t.contains(e.target)&&r.classList.add(`hidden`)}),a.addEventListener(`click`,async t=>{let n=t.target.closest(`[data-notification-id]`);if(n)try{await i(`${e}/${n.dataset.notificationId}/read`,{method:`POST`}),await s()}catch{}}),o?.addEventListener(`click`,async()=>{try{await i(`${e}/read-all`,{method:`POST`}),await s()}catch{}}),s(),setInterval(s,S)}function T(e){let t=Number(e||0);return`${new Intl.NumberFormat(`es-CO`,{style:`currency`,currency:`COP`,maximumFractionDigits:0}).format(t)} COP`}function te(e){let t=x(e.title);return`
        <a href="/producto/${e.slug}" class="block bg-white rounded-xl border border-slate-200 overflow-hidden hover:shadow-lg transition group">
            <div class="aspect-[4/3] bg-slate-100 overflow-hidden">
                <img src="${e.image_url||`https://placehold.co/600x450?text=Mercova`}" alt="${t}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
            </div>
            <div class="p-4">
                <span class="inline-block text-xs font-semibold uppercase tracking-wide text-emerald-600 mb-1">${e.transaction_type===`rent`?`Alquiler`:`Venta`}</span>
                <h3 class="font-semibold text-slate-800 truncate">${t}</h3>
                <p class="text-emerald-700 font-bold mt-1">${T(e.price)}</p>
            </div>
        </a>
    `}function ne(e){let t=x(e.title);return`
        <a href="/marketplace?type=services" class="block bg-white rounded-xl border border-slate-200 overflow-hidden hover:shadow-lg transition group">
            <div class="aspect-[4/3] bg-slate-100 overflow-hidden">
                <img src="${e.image_url||`https://placehold.co/600x450?text=Mercova`}" alt="${t}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
            </div>
            <div class="p-4">
                <span class="inline-block text-xs font-semibold uppercase tracking-wide text-indigo-600 mb-1">Servicio · ${x(e.duration)}</span>
                <h3 class="font-semibold text-slate-800 truncate">${t}</h3>
                <p class="text-indigo-700 font-bold mt-1">${T(e.price)}</p>
            </div>
        </a>
    `}async function re(){let e=document.getElementById(`home-categories`),t=document.getElementById(`home-featured-products`),n=document.getElementById(`home-featured-services`);try{let t=await i(`/categories`);e&&(e.innerHTML=t.map(e=>`
                <a href="/marketplace?category_id=${e.id}" class="px-4 py-2 rounded-full bg-white border border-slate-200 text-sm font-medium text-slate-600 hover:border-emerald-500 hover:text-emerald-600 transition whitespace-nowrap">
                    ${x(e.name)}
                </a>
            `).join(``))}catch{}try{let{data:e}=await i(`/products?per_page=4`),n=(e.data||[]).slice(0,4);t&&(t.innerHTML=n.length?n.map(te).join(``):`<p class="text-slate-500 col-span-full">Todavía no hay productos publicados.</p>`)}catch{t&&(t.innerHTML=`<p class="text-slate-500 col-span-full">No pudimos cargar los productos.</p>`)}try{let{data:e}=await i(`/services?per_page=3`),t=(e.data||[]).slice(0,3);n&&(n.innerHTML=t.length?t.map(ne).join(``):`<p class="text-slate-500 col-span-full">Todavía no hay servicios publicados.</p>`)}catch{n&&(n.innerHTML=`<p class="text-slate-500 col-span-full">No pudimos cargar los servicios.</p>`)}}function E(e,t=`info`){let n=document.getElementById(`toast-container`);if(!n)return;let r={info:`bg-slate-800`,success:`bg-emerald-600`,error:`bg-red-600`},i=document.createElement(`div`);i.className=`${r[t]||r.info} text-white text-sm font-medium px-4 py-3 rounded-lg shadow-lg transition-opacity duration-300`,i.textContent=e,n.appendChild(i),setTimeout(()=>{i.classList.add(`opacity-0`),setTimeout(()=>i.remove(),300)},3500)}function ie(){let e=document.getElementById(`login-form`);e&&e.addEventListener(`submit`,async t=>{t.preventDefault();let n=e.querySelector(`button[type="submit"]`);n.disabled=!0,n.textContent=`Ingresando...`;try{let t=new FormData(e),n=await c(t.get(`email`),t.get(`password`));if(E(`Inicio de sesión correcto.`,`success`),n.role===`admin`){window.location.href=`/admin`;return}let r=new URLSearchParams(window.location.search);window.location.href=r.get(`next`)||`/panel`}catch(e){E(e.message,`error`),n.disabled=!1,n.textContent=`Iniciar sesión`}})}function ae(){let e=document.getElementById(`register-form`);e&&e.addEventListener(`submit`,async t=>{t.preventDefault();let n=e.querySelector(`button[type="submit"]`);n.disabled=!0,n.textContent=`Creando cuenta...`;try{let t=new FormData(e),n=t.get(`role`)||`customer`;await l({name:t.get(`name`),email:t.get(`email`),password:t.get(`password`),password_confirmation:t.get(`password_confirmation`),phone:t.get(`phone`)||null,role:n}),E(`Cuenta creada con éxito. ¡Bienvenido a Mercova!`,`success`),window.location.href=n===`seller`?`/panel`:`/marketplace`}catch(e){E(e.message,`error`),n.disabled=!1,n.textContent=`Crear cuenta`}})}function D(e,t){let n=document.getElementById(`mercova-modal`);n&&n.remove();let r=document.createElement(`div`);r.id=`mercova-modal`,r.className=`fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4`,r.innerHTML=`
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 relative">
            <button type="button" data-modal-close class="absolute top-3 right-3 text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
            <h3 class="text-lg font-bold text-slate-800 mb-4">${x(e)}</h3>
            <div data-modal-body>${t}</div>
        </div>
    `,document.body.appendChild(r);let i=()=>r.remove();return r.addEventListener(`click`,e=>{e.target===r&&i()}),r.querySelector(`[data-modal-close]`).addEventListener(`click`,i),{overlay:r,close:i,body:r.querySelector(`[data-modal-body]`)}}async function oe(){let{data:e}=await i(`/favorites`);return e}async function O(e){return i(`/favorites`,{method:`POST`,body:JSON.stringify({product_id:e})})}async function k(e){return i(`/favorites/${e}`,{method:`DELETE`})}async function se(e,t,n){return i(`/reviews`,{method:`POST`,body:JSON.stringify({product_id:e,rating:t,comment:n||null})})}function A(e,t){if(!t)return`<span class="text-xs text-slate-400">Sin calificaciones todavía</span>`;let n=Math.round(e);return`<span class="text-amber-500 text-sm">${Array.from({length:5},(e,t)=>t<n?`★`:`☆`).join(``)}</span> <span class="text-xs text-slate-400">${e.toFixed(1)} (${t})</span>`}var j={type:`products`,categoryId:``,transactionType:``,search:``};function ce(){return t()?!0:(window.location.href=`/login?next=%2Fmarketplace`,!1)}function M(e){let t=e.transaction_type===`rent`,n=t?`Alquiler`:`Venta`,r=!!e.is_favorited,i=t?`${T(e.price)}/día`:T(e.price),a=x(e.title),o=x(e.description),s=x(e.location||``),c=x(e.business?.name||``),l=t?`
            <button type="button" data-action="rent" data-id="${e.id}" data-title="${a}"
                class="w-full bg-indigo-600 text-white text-sm font-semibold rounded-lg py-2 hover:bg-indigo-700 transition">
                Solicitar alquiler
            </button>
        `:`
            <div class="flex gap-2">
                <button type="button" data-action="add-to-cart" data-id="${e.id}"
                    class="flex-1 border border-emerald-600 text-emerald-700 text-sm font-semibold rounded-lg py-2 hover:bg-emerald-50 transition">
                    Agregar al carrito
                </button>
                <button type="button" data-action="order" data-id="${e.id}" data-title="${a}"
                    class="flex-1 bg-emerald-600 text-white text-sm font-semibold rounded-lg py-2 hover:bg-emerald-700 transition">
                    Comprar
                </button>
            </div>
        `;return`
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden flex flex-col">
            <a href="/producto/${e.slug}" class="aspect-[4/3] bg-slate-100 relative block">
                <img src="${e.image_url||`https://placehold.co/600x450?text=Mercova`}" alt="${a}" class="w-full h-full object-cover">
                <button type="button" data-action="toggle-favorite" data-id="${e.id}" data-favorited="${r?`1`:`0`}"
                    class="absolute top-2 right-2 h-8 w-8 rounded-full bg-white/90 flex items-center justify-center shadow hover:scale-105 transition">
                    <svg class="h-4 w-4 ${r?`fill-red-500 text-red-500`:`fill-none text-slate-400`}" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-7-4.35-9.5-8.5C.5 9 2 5 5.5 5c2 0 3.5 1.2 4.5 2.8C11 6.2 12.5 5 14.5 5 18 5 19.5 9 19.5 12.5 17 16.65 12 21 12 21z" />
                    </svg>
                </button>
            </a>
            <div class="p-4 flex flex-col flex-1">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-emerald-600">${n}</span>
                    ${c?`<span class="text-xs text-indigo-500 font-medium">${c}</span>`:``}
                </div>
                <h3 class="font-semibold text-slate-800"><a href="/producto/${e.slug}" class="hover:text-emerald-600">${a}</a></h3>
                <p class="text-sm text-slate-500 mt-1 line-clamp-2">${o}</p>
                <div class="mt-1">${A(Number(e.reviews_avg_rating)||0,e.reviews_count||0)}</div>
                <div class="mt-3 flex items-center justify-between">
                    <span class="text-emerald-700 font-bold">${i}</span>
                    <span class="text-xs text-slate-400">${s}</span>
                </div>
                <div class="mt-4">${l}</div>
            </div>
        </div>
    `}function le(e){let t=x(e.title),n=x(e.description),r=x(e.location||``),i=x(e.business?.name||``),a=x(e.duration);return`
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden flex flex-col">
            <div class="aspect-[4/3] bg-slate-100">
                <img src="${e.image_url||`https://placehold.co/600x450?text=Mercova`}" alt="${t}" class="w-full h-full object-cover">
            </div>
            <div class="p-4 flex flex-col flex-1">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Servicio · ${a}</span>
                    ${i?`<span class="text-xs text-indigo-500 font-medium">${i}</span>`:``}
                </div>
                <h3 class="font-semibold text-slate-800">${t}</h3>
                <p class="text-sm text-slate-500 mt-1 line-clamp-2">${n}</p>
                <div class="mt-3 flex items-center justify-between">
                    <span class="text-indigo-700 font-bold">${T(e.price)}</span>
                    <span class="text-xs text-slate-400">${r}</span>
                </div>
                <button type="button" data-action="book" data-id="${e.id}" data-title="${t}" data-price="${e.price}"
                    class="mt-4 w-full bg-indigo-600 text-white text-sm font-semibold rounded-lg py-2 hover:bg-indigo-700 transition">
                    Reservar
                </button>
            </div>
        </div>
    `}async function ue(){let e=document.getElementById(`filter-category`);if(e)try{e.innerHTML=`<option value="">Todas las categorías</option>`+(await i(`/categories`)).map(e=>`<option value="${e.id}">${x(e.name)}</option>`).join(``);let t=new URLSearchParams(window.location.search);t.get(`category_id`)&&(e.value=t.get(`category_id`),j.categoryId=t.get(`category_id`))}catch{}}async function N(){let e=document.getElementById(`listing-grid`),t=document.getElementById(`listing-empty`);if(!e)return;e.innerHTML=`<p class="col-span-full text-slate-400 text-center py-10">Cargando...</p>`,t?.classList.add(`hidden`);let n=new URLSearchParams;j.categoryId&&n.set(`category_id`,j.categoryId),j.search&&n.set(`search`,j.search),j.type===`products`&&j.transactionType&&n.set(`transaction_type`,j.transactionType);try{let{data:r}=await i(`/${j.type}?${n.toString()}`),a=r.data||[];if(!a.length){e.innerHTML=``,t?.classList.remove(`hidden`);return}e.innerHTML=a.map(j.type===`products`?M:le).join(``)}catch(t){e.innerHTML=`<p class="col-span-full text-red-500 text-center py-10">${t.message}</p>`}}async function P(e,t){let{data:n}=await i(`/payment-methods`),r=Object.entries(n).map(([e,t])=>`<option value="${e}">${t}</option>`).join(``),{body:a,close:o}=D(`Comprar "${t}"`,`
        <form id="order-form" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Cantidad</label>
                <input type="number" name="quantity" min="1" value="1" required class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Método de pago</label>
                <select name="payment_method" class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                    ${r}
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Notas (opcional)</label>
                <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-300 focus:border-emerald-500 focus:ring-emerald-500"></textarea>
            </div>
            <button type="submit" class="w-full bg-emerald-600 text-white font-semibold rounded-lg py-2 hover:bg-emerald-700 transition">Confirmar compra</button>
        </form>
    `);a.querySelector(`#order-form`).addEventListener(`submit`,async t=>{t.preventDefault();let n=new FormData(t.target);try{await i(`/orders`,{method:`POST`,body:JSON.stringify({product_id:e,quantity:Number(n.get(`quantity`)),payment_method:n.get(`payment_method`),notes:n.get(`notes`)||null})}),E(`¡Compra realizada con éxito!`,`success`),o()}catch(e){E(e.message,`error`)}})}async function F(e,t){let{data:n}=await i(`/payment-methods`),r=Object.entries(n).map(([e,t])=>`<option value="${e}">${t}</option>`).join(``),a=new Date().toISOString().slice(0,10),{body:o,close:s}=D(`Solicitar alquiler de "${t}"`,`
        <form id="rental-form" class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Desde</label>
                    <input type="date" name="start_date" min="${a}" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Hasta</label>
                    <input type="date" name="end_date" min="${a}" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>
            <p id="rental-availability" class="text-sm"></p>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Método de pago</label>
                <select name="payment_method" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
                    ${r}
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Notas (opcional)</label>
                <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            </div>
            <button type="submit" id="rental-submit" class="w-full bg-indigo-600 text-white font-semibold rounded-lg py-2 hover:bg-indigo-700 transition" disabled>
                Enviar solicitud
            </button>
        </form>
    `),c=o.querySelector(`#rental-form`),l=o.querySelector(`#rental-availability`),u=o.querySelector(`#rental-submit`);async function d(){let t=c.elements.start_date.value,n=c.elements.end_date.value;if(!t||!n||n<t){l.textContent=``,u.disabled=!0;return}try{let{data:r}=await i(`/products/${e}/availability?start_date=${t}&end_date=${n}`);r.available?(l.textContent=`Disponible para esas fechas.`,l.className=`text-sm text-emerald-600`,u.disabled=!1):(l.textContent=`Ya reservado en esas fechas. Elige otro rango.`,l.className=`text-sm text-red-500`,u.disabled=!0)}catch{l.textContent=``,u.disabled=!0}}c.elements.start_date.addEventListener(`change`,d),c.elements.end_date.addEventListener(`change`,d),c.addEventListener(`submit`,async t=>{t.preventDefault();let n=new FormData(c);try{await i(`/rentals`,{method:`POST`,body:JSON.stringify({product_id:Number(e),start_date:n.get(`start_date`),end_date:n.get(`end_date`),payment_method:n.get(`payment_method`),notes:n.get(`notes`)||null})}),E(`Solicitud de alquiler enviada. El propietario debe confirmarla.`,`success`),s()}catch(e){E(e.message,`error`)}})}function de(e,t){let{body:n,close:r}=D(`Reservar "${t}"`,`
        <form id="booking-form" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Fecha y hora</label>
                <input type="datetime-local" name="scheduled_at" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Cantidad de personas</label>
                <input type="number" name="guest_count" min="1" value="1" required class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Notas (opcional)</label>
                <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            </div>
            <button type="submit" class="w-full bg-indigo-600 text-white font-semibold rounded-lg py-2 hover:bg-indigo-700 transition">Confirmar reserva</button>
        </form>
    `);n.querySelector(`#booking-form`).addEventListener(`submit`,async t=>{t.preventDefault();let n=new FormData(t.target);try{await i(`/services/${e}/book`,{method:`POST`,body:JSON.stringify({scheduled_at:n.get(`scheduled_at`),guest_count:Number(n.get(`guest_count`)),notes:n.get(`notes`)||null})}),E(`¡Reserva realizada con éxito!`,`success`),r()}catch(e){E(e.message,`error`)}})}async function fe(){let e=document.getElementById(`listing-grid`);if(!e)return;let t=new URLSearchParams(window.location.search);t.get(`type`)===`services`&&(j.type=`services`),t.get(`transaction_type`)&&(j.transactionType=t.get(`transaction_type`)),t.get(`search`)&&(j.search=t.get(`search`));let n=document.getElementById(`tab-products`),r=document.getElementById(`tab-services`),i=document.getElementById(`filter-transaction-type`),a=document.getElementById(`filter-search`),o=document.getElementById(`filter-category`),s=()=>{let e=j.type===`products`;n.classList.toggle(`bg-emerald-600`,e),n.classList.toggle(`text-white`,e),n.classList.toggle(`text-slate-600`,!e),r.classList.toggle(`bg-indigo-600`,!e),r.classList.toggle(`text-white`,!e),r.classList.toggle(`text-slate-600`,e),i?.classList.toggle(`hidden`,!e)};n?.addEventListener(`click`,()=>{j.type=`products`,s(),N()}),r?.addEventListener(`click`,()=>{j.type=`services`,s(),N()}),o?.addEventListener(`change`,e=>{j.categoryId=e.target.value,N()}),i?.addEventListener(`change`,e=>{j.transactionType=e.target.value,N()});let c;a?.addEventListener(`input`,e=>{clearTimeout(c),c=setTimeout(()=>{j.search=e.target.value,N()},350)}),e.addEventListener(`click`,async e=>{let t=e.target.closest(`[data-action]`);if(!t||(e.preventDefault(),!ce()))return;let{action:n,id:r,title:i}=t.dataset;if(n===`order`&&P(r,i),n===`book`&&de(r,i),n===`rent`&&F(r,i),n===`add-to-cart`)try{await g(Number(r),1),await y(),E(`Producto agregado al carrito.`,`success`)}catch(e){E(e.message,`error`)}if(n===`toggle-favorite`){let e=t.dataset.favorited===`1`;try{e?await k(r):await O(r),t.dataset.favorited=e?`0`:`1`;let n=t.querySelector(`svg`);n.classList.toggle(`fill-red-500`,!e),n.classList.toggle(`text-red-500`,!e),n.classList.toggle(`fill-none`,e),n.classList.toggle(`text-slate-400`,e)}catch(e){E(e.message,`error`)}}}),s(),a&&(a.value=j.search),await ue(),await N()}function pe(e,{activePlanId:t,canSubscribe:n}={}){let r=t===e.id,i=(e.features||[]).map(e=>`<li class="flex gap-2 items-start"><span class="text-emerald-500">✓</span><span>${x(e)}</span></li>`).join(``);return`
        <div class="bg-white rounded-2xl border ${r?`border-emerald-500 ring-2 ring-emerald-100`:`border-slate-200`} p-6 flex flex-col">
            ${r?`<span class="self-start mb-2 text-xs font-semibold uppercase tracking-wide bg-emerald-100 text-emerald-700 px-2 py-1 rounded-full">Plan actual</span>`:``}
            <h3 class="text-lg font-bold text-slate-800">${x(e.name)}</h3>
            <p class="text-slate-500 text-sm mt-1 mb-4">${x(e.description||``)}</p>
            <p class="text-3xl font-extrabold text-slate-900 mb-1">
                ${Number(e.price)===0?`Gratis`:T(e.price)}
                ${Number(e.price)>0?`<span class="text-sm font-medium text-slate-400">/ ${e.billing_period}</span>`:``}
            </p>
            <ul class="text-sm text-slate-600 space-y-2 my-4 flex-1">${i}</ul>
            <button type="button" data-action="subscribe" data-plan-id="${e.id}"
                class="w-full rounded-lg py-2 font-semibold transition ${r?`bg-slate-100 text-slate-400 cursor-not-allowed`:`bg-emerald-600 text-white hover:bg-emerald-700`}"
                ${r||!n?`disabled`:``}>
                ${r?`Plan activo`:`Elegir plan`}
            </button>
        </div>
    `}function me(e){let t=x(e.name);return`
        <a href="/marketplace" class="block bg-white rounded-xl border border-slate-200 p-4 flex items-center gap-4 hover:shadow-md transition">
            <img src="${e.logo_url||`https://placehold.co/80x80?text=Biz`}" alt="${t}" class="w-14 h-14 rounded-lg object-cover bg-slate-100">
            <div>
                <p class="font-semibold text-slate-800">${t}</p>
                <p class="text-xs text-slate-500">${x(e.category||`Emprendimiento`)}</p>
            </div>
        </a>
    `}async function I(e){let t=document.getElementById(`plans-grid`);if(t)try{let{data:n}=await i(`/membership-plans`);t.innerHTML=n.map(t=>pe(t,e)).join(``),t.querySelectorAll(`[data-action="subscribe"]`).forEach(t=>{t.addEventListener(`click`,()=>e.onSubscribe(t.dataset.planId))})}catch(e){t.innerHTML=`<p class="text-red-500 col-span-full">${e.message}</p>`}}async function he(){let e=document.getElementById(`business-directory`);if(e)try{let{data:t}=await i(`/businesses`);e.innerHTML=t.length?t.map(me).join(``):`<p class="text-slate-400 col-span-full">Todavía no hay emprendimientos activos. ¡Sé el primero!</p>`}catch(t){e.innerHTML=`<p class="text-red-500 col-span-full">${t.message}</p>`}}function L(e){let t=document.getElementById(`business-panel`),n=document.getElementById(`business-form-wrapper`);if(!t||!n)return;if(!e){t.classList.add(`hidden`),n.classList.remove(`hidden`);return}n.classList.add(`hidden`),t.classList.remove(`hidden`);let r=e.status===`active`,i=e.membership_expires_at?new Date(e.membership_expires_at).toLocaleDateString(`es-CO`):null,a=x(e.name);t.innerHTML=`
        <div class="bg-white rounded-xl border border-slate-200 p-6 flex flex-col sm:flex-row sm:items-center gap-4 justify-between">
            <div class="flex items-center gap-4">
                <img src="${e.logo_url||`https://placehold.co/64x64?text=Biz`}" class="w-16 h-16 rounded-lg object-cover bg-slate-100" alt="${a}">
                <div>
                    <p class="font-bold text-lg text-slate-800">${a}</p>
                    <p class="text-sm text-slate-500">${x(e.category||``)}</p>
                    <span class="inline-block mt-1 text-xs font-semibold px-2 py-1 rounded-full ${r?`bg-emerald-100 text-emerald-700`:`bg-amber-100 text-amber-700`}">
                        ${r?`Membresía activa${i?` · vence ${i}`:``}`:`Sin membresía activa`}
                    </span>
                </div>
            </div>
            <a href="/panel" class="px-4 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 transition text-center">
                Publicar productos y servicios
            </a>
        </div>
    `}async function ge(){if(!document.getElementById(`plans-grid`))return;let e=await s(),n=document.getElementById(`business-section`),r=document.getElementById(`emprendimientos-guest-cta`),a=null;if(e){r?.classList.add(`hidden`),n?.classList.remove(`hidden`);try{let{data:e}=await i(`/my-business`);a=e}catch{a=null}L(a);let e=document.getElementById(`business-form`);e?.addEventListener(`submit`,async t=>{t.preventDefault();let n=new FormData(e);try{let{data:e}=await i(`/businesses`,{method:`POST`,body:JSON.stringify({name:n.get(`name`),description:n.get(`description`)||null,category:n.get(`category`)||null,phone:n.get(`phone`)||null,address:n.get(`address`)||null})});E(`Emprendimiento registrado. Elige tu membresía para publicar.`,`success`),a=e,L(a),await I({activePlanId:null,canSubscribe:!0,onSubscribe:e=>o(e)})}catch(e){E(e.message,`error`)}})}else n?.classList.add(`hidden`),r?.classList.remove(`hidden`);async function o(e){if(!a){E(`Registra tu emprendimiento antes de elegir una membresía.`,`error`);return}try{let{data:n}=await i(`/businesses/${a.id}/subscribe`,{method:`POST`,body:JSON.stringify({membership_plan_id:Number(e)})});E(`¡Membresía activada con éxito!`,`success`),a=n,L(a),await I({activePlanId:a.membership_plan_id,canSubscribe:!!t(),onSubscribe:o})}catch(e){E(e.message,`error`)}}await I({activePlanId:a?.membership_plan_id??null,canSubscribe:!!e,onSubscribe:e=>{if(!t()){window.location.href=`/login?next=%2Femprendimientos`;return}o(e)}}),await he()}var R=null,z=null,B=[];function V(e){let t={active:`bg-emerald-100 text-emerald-700`,draft:`bg-slate-100 text-slate-600`,archived:`bg-amber-100 text-amber-700`};return`<span class="text-xs font-semibold px-2 py-1 rounded-full ${t[e]||t.draft}">${e}</span>`}function H(e,t){let n=e.status===`active`?`archived`:`active`,r=t===`products`&&e.transaction_type===`rent`,i=r?`${T(e.price)}/día`:T(e.price),a=e.net_amount==null?``:` · recibes ${T(e.net_amount)}${r?`/día`:``}`;return`
        <div class="flex items-center justify-between gap-3 py-3 border-b border-slate-100 last:border-0">
            <div class="min-w-0">
                <p class="font-medium text-slate-800 truncate">${x(e.title)}</p>
                <p class="text-xs text-slate-400">${i}${a} · ${V(e.status)}</p>
            </div>
            <div class="flex gap-2 shrink-0">
                <button data-action="toggle-status" data-type="${t}" data-id="${e.id}" data-next="${n}"
                    class="text-xs font-medium text-indigo-600 hover:underline">${n===`active`?`Activar`:`Archivar`}</button>
                <button data-action="delete" data-type="${t}" data-id="${e.id}"
                    class="text-xs font-medium text-red-500 hover:underline">Eliminar</button>
            </div>
        </div>
    `}function U(e){let t={pending:`bg-amber-100 text-amber-700`,confirmed:`bg-blue-100 text-blue-700`,active:`bg-emerald-100 text-emerald-700`,finished:`bg-slate-100 text-slate-600`,cancelled:`bg-red-100 text-red-600`};return`<span class="text-xs font-semibold px-2 py-1 rounded-full ${t[e]||t.pending}">${{pending:`Pendiente`,confirmed:`Confirmado`,active:`Activo`,finished:`Finalizado`,cancelled:`Cancelado`}[e]||e}</span>`}function _e(e){let t=x(e.product?.title??`Producto`);return`
        <div class="flex items-center justify-between gap-3 py-3 border-b border-slate-100 last:border-0">
            <div class="min-w-0">
                <p class="font-medium text-slate-800 truncate">${t}</p>
                <p class="text-xs text-slate-400">${e.start_date} → ${e.end_date} · ${T(e.total)}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                ${U(e.status)}
                ${e.status===`pending`?`<button data-action="cancel-rental" data-id="${e.id}" class="text-xs font-medium text-red-500 hover:underline">Cancelar</button>`:``}
                ${e.status===`finished`&&e.product?`<button data-action="review" data-product-id="${e.product.id}" data-title="${t}" class="text-xs font-medium text-amber-600 hover:underline">Calificar</button>`:``}
            </div>
        </div>
    `}function ve(e){let t=({pending:[[`confirmed`,`Confirmar`,`text-emerald-600`],[`cancelled`,`Rechazar`,`text-red-500`]],confirmed:[[`active`,`Marcar activo`,`text-emerald-600`],[`cancelled`,`Cancelar`,`text-red-500`]],active:[[`finished`,`Finalizar`,`text-emerald-600`]]}[e.status]||[]).map(([t,n,r])=>`
        <button data-action="update-rental" data-id="${e.id}" data-status="${t}" class="text-xs font-medium ${r} hover:underline">${n}</button>
    `).join(``);return`
        <div class="flex items-center justify-between gap-3 py-3 border-b border-slate-100 last:border-0">
            <div class="min-w-0">
                <p class="font-medium text-slate-800 truncate">${x(e.product?.title??`Producto`)}</p>
                <p class="text-xs text-slate-400">${x(e.renter?.name??``)} · ${e.start_date} → ${e.end_date} · ${T(e.total)} · recibes ${T(e.net_amount)}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                ${U(e.status)}
                ${t}
            </div>
        </div>
    `}function ye(e){let t=e.status===`completed`?(e.items||[]).filter(e=>e.product).map(e=>{let t=x(e.product.title);return`
                    <button data-action="review" data-product-id="${e.product.id}" data-title="${t}"
                        class="text-xs font-medium text-amber-600 hover:underline">Calificar "${t}"</button>
                `}).join(``):``;return`
        <div class="py-3 border-b border-slate-100 last:border-0">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-medium text-slate-800">Pedido #${e.id}</p>
                    <p class="text-xs text-slate-400">${new Date(e.created_at).toLocaleDateString(`es-CO`)} · ${T(e.total)}</p>
                </div>
                ${V(e.status)}
            </div>
            ${t?`<div class="mt-2 flex flex-wrap gap-3">${t}</div>`:``}
        </div>
    `}function be(e){return`
        <div class="flex items-center justify-between py-3 border-b border-slate-100 last:border-0">
            <div>
                <p class="font-medium text-slate-800">Reserva #${e.id}</p>
                <p class="text-xs text-slate-400">${new Date(e.scheduled_at).toLocaleString(`es-CO`)} · ${T(e.total)}</p>
            </div>
            ${V(e.status)}
        </div>
    `}function xe(){let e=document.getElementById(`dashboard-profile`);e&&(e.innerHTML=`
        <p class="text-xl font-bold text-slate-800">${x(R.name)}</p>
        <p class="text-sm text-slate-500">${x(R.email)}</p>
        <span class="inline-block mt-2 text-xs font-semibold px-2 py-1 rounded-full bg-emerald-100 text-emerald-700">${o(R.role)}</span>
    `)}function Se(){let e=document.getElementById(`dashboard-listings`);if(!e)return;let t=(R.products||[]).map(e=>H(e,`products`)),n=(R.services||[]).map(e=>H(e,`services`)),r=[...t,...n];e.innerHTML=r.length?r.join(``):`<p class="text-slate-400 text-sm py-4">Todavía no tienes publicaciones. Crea una abajo.</p>`}function Ce(){let e=document.getElementById(`dashboard-orders`);if(!e)return;let t=R.orders||[];e.innerHTML=t.length?t.map(ye).join(``):`<p class="text-slate-400 text-sm py-4">Aún no has realizado compras.</p>`}function we(){let e=document.getElementById(`dashboard-bookings`);if(!e)return;let t=R.bookings||[];e.innerHTML=t.length?t.map(be).join(``):`<p class="text-slate-400 text-sm py-4">Aún no tienes reservas.</p>`}async function Te(){let e=document.getElementById(`dashboard-rentals`);if(e)try{let{data:t}=await i(`/rentals`);e.innerHTML=t.length?t.map(_e).join(``):`<p class="text-slate-400 text-sm py-4">Aún no has solicitado ningún alquiler.</p>`}catch{e.innerHTML=`<p class="text-red-500 text-sm py-4">No pudimos cargar tus alquileres.</p>`}}async function Ee(){let e=document.getElementById(`owned-rentals-section`),t=document.getElementById(`dashboard-owned-rentals`);if(t&&e){if(![`seller`,`admin`].includes(R.role)){e.classList.add(`hidden`);return}try{let{data:n}=await i(`/rentals/owned`);e.classList.remove(`hidden`),t.innerHTML=n.length?n.map(ve).join(``):`<p class="text-slate-400 text-sm py-4">No tienes solicitudes de alquiler recibidas.</p>`}catch{e.classList.add(`hidden`)}}}function De(e,t){let{body:n,close:r}=D(`Calificar "${t}"`,`
        <form id="review-form" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Tu calificación</label>
                <div id="star-picker" class="flex gap-1 text-2xl text-slate-300">
                    ${[1,2,3,4,5].map(e=>`<button type="button" data-star="${e}" class="hover:text-amber-400 transition">★</button>`).join(``)}
                </div>
                <input type="hidden" name="rating" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Comentario (opcional)</label>
                <textarea name="comment" rows="3" class="w-full rounded-lg border-slate-300 text-sm focus:border-amber-500 focus:ring-amber-500"></textarea>
            </div>
            <button type="submit" class="w-full bg-amber-500 text-white font-semibold rounded-lg py-2 hover:bg-amber-600 transition">Enviar calificación</button>
        </form>
    `),i=n.querySelector(`#review-form`),a=i.elements.rating,o=n.querySelectorAll(`[data-star]`);o.forEach(e=>{e.addEventListener(`click`,()=>{let t=Number(e.dataset.star);a.value=t,o.forEach(e=>e.classList.toggle(`text-amber-400`,Number(e.dataset.star)<=t))})}),i.addEventListener(`submit`,async t=>{if(t.preventDefault(),!a.value){E(`Selecciona una calificación de 1 a 5 estrellas.`,`error`);return}try{await se(e,Number(a.value),new FormData(i).get(`comment`)),E(`Gracias por tu calificación.`,`success`),r()}catch(e){E(e.message,`error`)}})}function Oe(){[`dashboard-orders`,`dashboard-rentals`].forEach(e=>{document.getElementById(e)?.addEventListener(`click`,e=>{let t=e.target.closest(`[data-action="review"]`);t&&De(t.dataset.productId,t.dataset.title)})})}function ke(){document.getElementById(`dashboard-rentals`)?.addEventListener(`click`,async e=>{let t=e.target.closest(`[data-action="cancel-rental"]`);if(t&&confirm(`¿Cancelar esta solicitud de alquiler?`))try{await i(`/rentals/${t.dataset.id}/status`,{method:`PATCH`,body:JSON.stringify({status:`cancelled`})}),E(`Solicitud cancelada.`,`success`),await Te()}catch(e){E(e.message,`error`)}}),document.getElementById(`dashboard-owned-rentals`)?.addEventListener(`click`,async e=>{let t=e.target.closest(`[data-action="update-rental"]`);if(t)try{await i(`/rentals/${t.dataset.id}/status`,{method:`PATCH`,body:JSON.stringify({status:t.dataset.status})}),E(`Estado del alquiler actualizado.`,`success`),await Ee()}catch(e){E(e.message,`error`)}})}function Ae(){let e=document.getElementById(`profile-form`);e&&(e.elements.name.value=R.name||``,e.elements.phone.value=R.phone||``,e.elements.bio.value=R.bio||``,e.elements.avatar_url.value=R.avatar_url||``)}function je(){let e=document.getElementById(`profile-form`);e?.addEventListener(`submit`,async t=>{t.preventDefault();let n=new FormData(e);try{await i(`/me`,{method:`PUT`,body:JSON.stringify({name:n.get(`name`),phone:n.get(`phone`)||null,bio:n.get(`bio`)||null,avatar_url:n.get(`avatar_url`)||null})}),E(`Perfil actualizado.`,`success`),await W()}catch(e){E(e.message,`error`)}})}function Me(){let e=document.getElementById(`password-form`);e?.addEventListener(`submit`,async t=>{t.preventDefault();let n=new FormData(e);try{await i(`/me/password`,{method:`PUT`,body:JSON.stringify({current_password:n.get(`current_password`),password:n.get(`password`),password_confirmation:n.get(`password_confirmation`)})}),E(`Contraseña actualizada.`,`success`),e.reset()}catch(e){E(e.message,`error`)}})}function Ne(){document.getElementById(`dashboard-listings`)?.addEventListener(`click`,async e=>{let t=e.target.closest(`[data-action]`);if(!t)return;let{action:n,type:r,id:a,next:o}=t.dataset;try{if(n===`delete`){if(!confirm(`¿Eliminar esta publicación?`))return;await i(`/${r}/${a}`,{method:`DELETE`}),E(`Publicación eliminada.`,`success`)}n===`toggle-status`&&(await i(`/${r}/${a}`,{method:`PUT`,body:JSON.stringify({status:o})}),E(`Estado actualizado.`,`success`)),await W()}catch(e){E(e.message,`error`)}})}async function Pe(){let e=document.getElementById(`listing-category`);e&&(B=await i(`/categories`),e.innerHTML=B.map(e=>`<option value="${e.id}">${x(e.name)}</option>`).join(``))}function Fe(){let e=document.getElementById(`create-listing-section`);if(!e)return;if(![`seller`,`admin`].includes(R.role)){e.classList.add(`hidden`);return}e.classList.remove(`hidden`);let t=document.getElementById(`listing-business`);if(t){if(z&&z.status===`active`)t.innerHTML=`
                <option value="">Publicación personal</option>
                <option value="${z.id}">${x(z.name)} (emprendimiento)</option>
            `;else if(t.innerHTML=`<option value="">Publicación personal</option>`,z){let e=document.getElementById(`business-hint`);e&&(e.textContent=`Tu emprendimiento necesita una membresía activa para publicar a su nombre. Revisa la sección Emprendimientos.`)}}let n=document.querySelectorAll(`[data-listing-type]`),r=document.getElementById(`product-only-fields`),a=document.getElementById(`service-only-fields`),o=`products`,s=document.querySelectorAll(`[data-price-mode]`),c=document.getElementById(`price-mode-toggle`),l=document.getElementById(`listing-price-input`),u=document.getElementById(`commission-breakdown`),d=document.getElementById(`breakdown-gross-label`),f=document.getElementById(`sale-price-section`),p=document.getElementById(`rent-price-section`),m=document.getElementById(`rent-price-input`),h=document.getElementById(`listing-transaction-type`),g=document.getElementById(`stock-field`),_=`price`,v={price:`Ej: 50000 (lo que pagará el comprador)`,desired_net_price:`Ej: 50000 (lo que quieres recibir tú, neto)`},y=()=>o===`products`&&h.value===`rent`;function b(){if(o===`services`){f.classList.remove(`hidden`),p.classList.add(`hidden`),c.classList.remove(`hidden`),l.required=!0;return}let e=y();f.classList.toggle(`hidden`,e),p.classList.toggle(`hidden`,!e),c.classList.toggle(`hidden`,e),l.required=!e,g.classList.toggle(`hidden`,e)}async function S(){let e=y(),t=Number(e?m.value:l.value);if(!t||t<=0){u.classList.add(`hidden`);return}try{let{data:n}=await i(`/products/commission-preview`,{method:`POST`,body:JSON.stringify(e?{price:t}:{[_]:t})});d.textContent=e?`Precio por día`:`Valor publicado (lo que paga el comprador)`,document.getElementById(`breakdown-gross`).textContent=T(n.gross_price),document.getElementById(`breakdown-rate`).textContent=(n.commission_rate*100).toFixed(0),document.getElementById(`breakdown-commission`).textContent=`-${T(n.commission_amount)}`,document.getElementById(`breakdown-net`).textContent=T(n.net_amount),u.classList.remove(`hidden`)}catch{u.classList.add(`hidden`)}}s.forEach(e=>{e.addEventListener(`click`,()=>{_=e.dataset.priceMode,s.forEach(t=>t.classList.toggle(`bg-white`,t===e)),l.placeholder=v[_],S()})}),l?.addEventListener(`input`,S),m?.addEventListener(`input`,S),h?.addEventListener(`change`,()=>{b(),S()}),n.forEach(e=>{e.addEventListener(`click`,()=>{o=e.dataset.listingType,n.forEach(t=>t.classList.toggle(`bg-white`,t===e)),r.classList.toggle(`hidden`,o!==`products`),a.classList.toggle(`hidden`,o!==`services`),_=`price`,s.forEach(e=>e.classList.toggle(`bg-white`,e.dataset.priceMode===`price`)),l.placeholder=v.price,b(),S()})}),b();let C=document.getElementById(`create-listing-form`);C.addEventListener(`submit`,async e=>{e.preventDefault();let t=new FormData(C),n={category_id:Number(t.get(`category_id`)),business_id:t.get(`business_id`)||null,title:t.get(`title`),description:t.get(`description`),location:t.get(`location`)||null,image_url:t.get(`image_url`)||null};if(o===`products`){if(n.transaction_type=t.get(`transaction_type`),n.condition=t.get(`condition`)||null,y()){n.price_per_day=Number(t.get(`price_per_day`));let e=t.get(`deposit_amount`);n.deposit_amount=e?Number(e):null}else n[_]=Number(t.get(`price`)),n.stock=Number(t.get(`stock`)||1)}else n[_]=Number(t.get(`price`)),n.duration=t.get(`duration`)||`1 hora`;try{await i(`/${o}`,{method:`POST`,body:JSON.stringify(n)}),E(`Publicación creada con éxito.`,`success`),C.reset(),u.classList.add(`hidden`),b(),await W()}catch(e){E(e.message,`error`)}})}async function W(){let{user:e}=await i(`/me`);R=e,xe(),Se(),Ce(),we(),Ae()}async function Ie(){if(document.getElementById(`dashboard-profile`)&&p()){try{await W()}catch{E(`No pudimos cargar tu panel. Inicia sesión nuevamente.`,`error`),window.location.href=`/login`;return}try{let{data:e}=await i(`/my-business`);z=e}catch{z=null}await Pe(),Fe(),Ne(),ke(),Oe(),je(),Me(),await Te(),await Ee()}}function Le(){let e=document.getElementById(`forgot-password-form`);e&&e.addEventListener(`submit`,async t=>{t.preventDefault();let n=e.querySelector(`button[type="submit"]`);n.disabled=!0,n.textContent=`Enviando...`;try{let t=new FormData(e);await i(`/forgot-password`,{method:`POST`,body:JSON.stringify({email:t.get(`email`)})}),E(`Si el correo existe, te enviamos un enlace de recuperación.`,`success`),e.reset()}catch(e){E(e.message,`error`)}finally{n.disabled=!1,n.textContent=`Enviar enlace`}})}function Re(){let e=document.getElementById(`reset-password-form`);if(!e)return;let t=new URLSearchParams(window.location.search),n=e.querySelector(`[name="token"]`),r=e.querySelector(`[name="email"]`);n&&(n.value=t.get(`token`)||``),r&&(r.value=t.get(`email`)||``),e.addEventListener(`submit`,async t=>{t.preventDefault();let n=e.querySelector(`button[type="submit"]`);n.disabled=!0,n.textContent=`Guardando...`;try{let t=new FormData(e);await i(`/reset-password`,{method:`POST`,body:JSON.stringify({token:t.get(`token`),email:t.get(`email`),password:t.get(`password`),password_confirmation:t.get(`password_confirmation`)})}),E(`Contraseña restablecida. Ya puedes iniciar sesión.`,`success`),window.location.href=`/login`}catch(e){E(e.message,`error`),n.disabled=!1,n.textContent=`Restablecer contraseña`}})}function G(e,t){return`
        <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
            <p class="text-2xl font-extrabold text-slate-800">${t}</p>
            <p class="text-xs text-slate-500 mt-1">${e}</p>
        </div>
    `}function ze(e){return`
        <div class="flex items-center justify-between py-3 border-b border-slate-100 last:border-0 text-sm">
            <div>
                <p class="font-medium text-slate-800">#${e.id} · ${x(e.buyer?.name??``)}</p>
                <p class="text-xs text-slate-400">${new Date(e.created_at).toLocaleString(`es-CO`)}</p>
            </div>
            <div class="text-right">
                <p class="font-semibold text-slate-800">${T(e.total)}</p>
                <p class="text-xs text-slate-400">${e.status}</p>
            </div>
        </div>
    `}function Be(e){return`
        <div class="py-3 border-b border-slate-100 last:border-0 text-sm">
            <p class="font-medium text-slate-800">${x(e.title)}</p>
            <p class="text-xs text-slate-500">${x(e.message||``)}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">${new Date(e.created_at).toLocaleString(`es-CO`)}</p>
        </div>
    `}async function Ve(){let e=document.getElementById(`admin-stats`);if(e&&await m()){w();try{let{data:t}=await i(`/admin/dashboard`),{stats:n,recent_orders:r,recent_activity:a}=t;e.innerHTML=[G(`Usuarios`,n.users),G(`Nuevos usuarios (24h)`,n.new_users),G(`Publicaciones`,n.products+n.services),G(`Publicaciones nuevas (24h)`,n.new_listings),G(`Pedidos totales`,n.orders),G(`Pedidos nuevos (24h)`,n.new_orders),G(`Pedidos pendientes`,n.pending_orders),G(`Pedidos en proceso`,n.processing_orders),G(`Reservas pendientes`,n.pending_bookings),G(`Reservas en proceso`,n.processing_bookings),G(`Emprendimientos activos`,n.active_businesses),G(`Notificaciones sin leer`,n.unread_notifications)].join(``),document.getElementById(`admin-recent-orders`).innerHTML=r.length?r.map(ze).join(``):`<p class="text-slate-400 text-sm py-4">Sin pedidos todavía.</p>`,document.getElementById(`admin-recent-activity`).innerHTML=a.length?a.map(Be).join(``):`<p class="text-slate-400 text-sm py-4">Sin actividad todavía.</p>`}catch(t){e.innerHTML=`<p class="col-span-full text-red-500 text-center py-8">${t.message}</p>`}}}var K={pending:`Pendiente`,processing:`En proceso`,completed:`Completado`,cancelled:`Cancelado`},He={pending:`bg-amber-100 text-amber-700`,processing:`bg-indigo-100 text-indigo-700`,completed:`bg-emerald-100 text-emerald-700`,cancelled:`bg-red-100 text-red-700`},q={type:`orders`,status:``,onlyNew:!1};function Ue(e,t){return`
        <select data-status-select="${e}" class="text-xs rounded-md border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
            ${Object.entries(K).map(([e,n])=>`<option value="${e}" ${e===t?`selected`:``}>${n}</option>`).join(``)}
        </select>
    `}function We(e){return`
        <tr class="border-b border-slate-100 last:border-0">
            <td class="px-4 py-3">#${e.id}</td>
            <td class="px-4 py-3">${x(e.buyer?.name??`—`)}</td>
            <td class="px-4 py-3">${x(e.seller?.name??`—`)}</td>
            <td class="px-4 py-3 font-medium">${T(e.total)}</td>
            <td class="px-4 py-3 text-slate-500">${new Date(e.created_at).toLocaleDateString(`es-CO`)}</td>
            <td class="px-4 py-3"><span class="text-xs font-semibold px-2 py-1 rounded-full ${He[e.status]||``}">${K[e.status]||e.status}</span></td>
            <td class="px-4 py-3">${Ue(e.id,e.status)}</td>
        </tr>
    `}function Ge(e){return`
        <tr class="border-b border-slate-100 last:border-0">
            <td class="px-4 py-3">#${e.id}</td>
            <td class="px-4 py-3">${x(e.buyer?.name??`—`)}</td>
            <td class="px-4 py-3">${x(e.service?.title??`—`)}</td>
            <td class="px-4 py-3 font-medium">${T(e.total)}</td>
            <td class="px-4 py-3 text-slate-500">${new Date(e.scheduled_at).toLocaleDateString(`es-CO`)}</td>
            <td class="px-4 py-3"><span class="text-xs font-semibold px-2 py-1 rounded-full ${He[e.status]||``}">${K[e.status]||e.status}</span></td>
            <td class="px-4 py-3">${Ue(e.id,e.status)}</td>
        </tr>
    `}async function J(){let e=document.getElementById(`orders-table-body`),t=document.getElementById(`col-extra`);t.textContent=q.type===`orders`?`Vendedor`:`Servicio`,e.innerHTML=`<tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Cargando...</td></tr>`;let n=new URLSearchParams;q.status&&n.set(`status`,q.status),q.onlyNew&&n.set(`window`,`new`);try{let{data:t}=await i(`/admin/${q.type}?${n.toString()}`);if(!t.length){e.innerHTML=`<tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Sin resultados.</td></tr>`;return}e.innerHTML=t.map(q.type===`orders`?We:Ge).join(``)}catch(t){e.innerHTML=`<tr><td colspan="7" class="px-4 py-8 text-center text-red-500">${t.message}</td></tr>`}}async function Ke(){let e=document.getElementById(`orders-table-body`);if(!e||!await m())return;w();let t=document.getElementById(`tab-orders`),n=document.getElementById(`tab-bookings`),r=document.getElementById(`filter-status`),a=document.getElementById(`filter-new`),o=()=>{let e=q.type===`orders`;t.classList.toggle(`bg-emerald-600`,e),t.classList.toggle(`text-white`,e),t.classList.toggle(`text-slate-600`,!e),n.classList.toggle(`bg-indigo-600`,!e),n.classList.toggle(`text-white`,!e),n.classList.toggle(`text-slate-600`,e)};t.addEventListener(`click`,()=>{q.type=`orders`,o(),J()}),n.addEventListener(`click`,()=>{q.type=`bookings`,o(),J()}),r.addEventListener(`change`,e=>{q.status=e.target.value,J()}),a.addEventListener(`change`,e=>{q.onlyNew=e.target.checked,J()}),e.addEventListener(`change`,async e=>{let t=e.target.closest(`[data-status-select]`);if(!t)return;let n=t.dataset.statusSelect,r=q.type===`orders`?`/admin/orders/${n}/status`:`/admin/bookings/${n}/status`;try{await i(r,{method:`PATCH`,body:JSON.stringify({status:t.value})}),E(`Estado actualizado.`,`success`),await J()}catch(e){E(e.message,`error`)}}),o(),await J()}var qe={active:`bg-emerald-100 text-emerald-700`,draft:`bg-slate-100 text-slate-600`,archived:`bg-amber-100 text-amber-700`};function Je(e,t){let n=t===`products`?e.transaction_type===`rent`?`Alquiler`:`Venta`:`Servicio`,r=e.status===`active`?`archived`:`active`;return`
        <tr class="border-b border-slate-100 last:border-0">
            <td class="px-4 py-3 font-medium text-slate-800">${x(e.title)}</td>
            <td class="px-4 py-3 text-slate-600">${x(e.seller?.name??`—`)}${e.business?` <span class="text-xs text-indigo-500">(${x(e.business.name)})</span>`:``}</td>
            <td class="px-4 py-3 text-slate-500">${n}</td>
            <td class="px-4 py-3 font-medium">${T(e.price)}</td>
            <td class="px-4 py-3"><span class="text-xs font-semibold px-2 py-1 rounded-full ${qe[e.status]||``}">${e.status}</span></td>
            <td class="px-4 py-3 space-x-2 whitespace-nowrap">
                <button data-action="toggle" data-type="${t}" data-id="${e.id}" data-next="${r}" class="text-xs font-medium text-indigo-600 hover:underline">
                    ${r===`active`?`Activar`:`Archivar`}
                </button>
                <button data-action="delete" data-type="${t}" data-id="${e.id}" class="text-xs font-medium text-red-500 hover:underline">Eliminar</button>
            </td>
        </tr>
    `}async function Ye(){let e=document.getElementById(`listings-table-body`);if(!e||!await m())return;w();async function t(){e.innerHTML=`<tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Cargando...</td></tr>`;try{let[t,n]=await Promise.all([i(`/admin/products`),i(`/admin/services`)]),r=[...t.data.map(e=>({item:e,type:`products`})),...n.data.map(e=>({item:e,type:`services`}))].sort((e,t)=>new Date(t.item.created_at)-new Date(e.item.created_at));e.innerHTML=r.length?r.map(({item:e,type:t})=>Je(e,t)).join(``):`<tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Sin publicaciones todavía.</td></tr>`}catch(t){e.innerHTML=`<tr><td colspan="6" class="px-4 py-8 text-center text-red-500">${t.message}</td></tr>`}}e.addEventListener(`click`,async e=>{let n=e.target.closest(`[data-action]`);if(!n)return;let{action:r,type:a,id:o,next:s}=n.dataset;try{if(r===`delete`){if(!confirm(`¿Eliminar esta publicación?`))return;await i(`/${a}/${o}`,{method:`DELETE`}),E(`Publicación eliminada.`,`success`)}r===`toggle`&&(await i(`/${a}/${o}`,{method:`PUT`,body:JSON.stringify({status:s})}),E(`Estado actualizado.`,`success`)),await t()}catch(e){E(e.message,`error`)}}),await t()}function Xe(e){let t=e.role===`admin`,n=e.is_active?`<span class="text-xs font-semibold px-2 py-1 rounded-full bg-emerald-100 text-emerald-700">Activo</span>`:`<span class="text-xs font-semibold px-2 py-1 rounded-full bg-red-100 text-red-700">Desactivado</span>`;return`
        <tr class="border-b border-slate-100 last:border-0">
            <td class="px-4 py-3 font-medium text-slate-800">${x(e.name)}</td>
            <td class="px-4 py-3 text-slate-500">${x(e.email)}</td>
            <td class="px-4 py-3 text-slate-600">${o(e.role)}</td>
            <td class="px-4 py-3 text-slate-600">${(e.products_count??0)+(e.services_count??0)}</td>
            <td class="px-4 py-3 text-slate-600">${e.orders_count??0}</td>
            <td class="px-4 py-3">${n}</td>
            <td class="px-4 py-3">
                ${t?`<span class="text-xs text-slate-400">—</span>`:`<button data-action="toggle" data-id="${e.id}" data-next="${e.is_active?`false`:`true`}" class="text-xs font-medium ${e.is_active?`text-red-500`:`text-emerald-600`} hover:underline">${e.is_active?`Desactivar`:`Activar`}</button>`}
            </td>
        </tr>
    `}async function Ze(){let e=document.getElementById(`users-table-body`);if(!e||!await m())return;w();let t=document.getElementById(`user-search`);async function n(t=``){e.innerHTML=`<tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Cargando...</td></tr>`;try{let{data:n}=await i(`/admin/users${t?`?search=${encodeURIComponent(t)}`:``}`);e.innerHTML=n.length?n.map(Xe).join(``):`<tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Sin resultados.</td></tr>`}catch(t){e.innerHTML=`<tr><td colspan="7" class="px-4 py-8 text-center text-red-500">${t.message}</td></tr>`}}let r;t?.addEventListener(`input`,e=>{clearTimeout(r),r=setTimeout(()=>n(e.target.value),350)}),e.addEventListener(`click`,async e=>{let r=e.target.closest(`[data-action="toggle"]`);if(r)try{await i(`/admin/users/${r.dataset.id}/status`,{method:`PATCH`,body:JSON.stringify({is_active:r.dataset.next===`true`})}),E(`Estado del usuario actualizado.`,`success`),await n(t?.value||``)}catch(e){E(e.message,`error`)}}),await n()}var Y=[];function Qe(e){let t=e.parent_id!==null,n=(e.products_count??0)+(e.services_count??0);return`
        <tr class="border-b border-slate-100 last:border-0">
            <td class="px-4 py-3 text-slate-800">${t?`<span class="text-slate-300">— </span>${x(e.name)}`:`<strong>${x(e.name)}</strong>`}</td>
            <td class="px-4 py-3 text-slate-500">${e.parent?x(e.parent.name):`<span class="text-slate-300">—</span>`}</td>
            <td class="px-4 py-3 text-slate-600">${n}</td>
            <td class="px-4 py-3 space-x-2 whitespace-nowrap">
                <button data-action="edit" data-id="${e.id}" class="text-xs font-medium text-indigo-600 hover:underline">Editar</button>
                <button data-action="delete" data-id="${e.id}" class="text-xs font-medium text-red-500 hover:underline">Eliminar</button>
            </td>
        </tr>
    `}function $e(e){let t=e.filter(e=>e.parent_id===null),n=[];return t.forEach(t=>{n.push(t),e.filter(e=>e.parent_id===t.id).forEach(e=>n.push(e))}),n}async function X(){let e=document.getElementById(`categories-table-body`);e.innerHTML=`<tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Cargando...</td></tr>`;try{Y=await i(`/categories`);let t=$e(Y);e.innerHTML=t.length?t.map(Qe).join(``):`<tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Sin categorías todavía.</td></tr>`;let n=document.querySelector(`select[name="parent_id"]`);n.innerHTML=`<option value="">Ninguna (categoría principal)</option>`+Y.filter(e=>e.parent_id===null).map(e=>`<option value="${e.id}">${x(e.name)}</option>`).join(``)}catch(t){e.innerHTML=`<tr><td colspan="4" class="px-4 py-8 text-center text-red-500">${t.message}</td></tr>`}}function et(e,t,n){e.reset(),e.elements.id.value=``,t.textContent=`Nueva categoría`,n.classList.add(`hidden`)}async function tt(){let e=document.getElementById(`categories-table-body`);if(!e||!await m())return;w();let t=document.getElementById(`category-form`),n=document.getElementById(`category-form-title`),r=document.getElementById(`category-form-cancel`);r.addEventListener(`click`,()=>et(t,n,r)),e.addEventListener(`click`,async e=>{let a=e.target.closest(`[data-action]`);if(!a)return;let{action:o,id:s}=a.dataset;if(o===`edit`){let e=Y.find(e=>e.id===Number(s));if(!e)return;t.elements.id.value=e.id,t.elements.name.value=e.name,t.elements.parent_id.value=e.parent_id??``,t.elements.icon.value=e.icon??``,t.elements.description.value=e.description??``,n.textContent=`Editar "${e.name}"`,r.classList.remove(`hidden`);return}if(o===`delete`){if(!confirm(`¿Eliminar esta categoría?`))return;try{await i(`/admin/categories/${s}`,{method:`DELETE`}),E(`Categoría eliminada.`,`success`),await X()}catch(e){E(e.message,`error`)}}}),t.addEventListener(`submit`,async e=>{e.preventDefault();let a=new FormData(t),o=a.get(`id`),s={name:a.get(`name`),parent_id:a.get(`parent_id`)||null,icon:a.get(`icon`)||null,description:a.get(`description`)||null};try{o?(await i(`/admin/categories/${o}`,{method:`PUT`,body:JSON.stringify(s)}),E(`Categoría actualizada.`,`success`)):(await i(`/admin/categories`,{method:`POST`,body:JSON.stringify(s)}),E(`Categoría creada.`,`success`)),et(t,n,r),await X()}catch(e){E(e.message,`error`)}}),await X()}var Z=[];function nt(e){let t=e.is_active?`<span class="text-xs font-semibold px-2 py-1 rounded-full bg-emerald-100 text-emerald-700">Activo</span>`:`<span class="text-xs font-semibold px-2 py-1 rounded-full bg-slate-100 text-slate-500">Inactivo</span>`;return`
        <tr class="border-b border-slate-100 last:border-0">
            <td class="px-4 py-3 font-medium text-slate-800">${x(e.name)}</td>
            <td class="px-4 py-3 text-slate-600">${Number(e.price)===0?`Gratis`:T(e.price)}${Number(e.price)>0?` / ${x(e.billing_period)}`:``}</td>
            <td class="px-4 py-3 text-slate-600">${e.max_listings??`Ilimitado`}</td>
            <td class="px-4 py-3 text-slate-600">${e.businesses_count??0}</td>
            <td class="px-4 py-3">${t}</td>
            <td class="px-4 py-3 space-x-2 whitespace-nowrap">
                <button data-action="edit" data-id="${e.id}" class="text-xs font-medium text-indigo-600 hover:underline">Editar</button>
                <button data-action="delete" data-id="${e.id}" class="text-xs font-medium text-red-500 hover:underline">Eliminar</button>
            </td>
        </tr>
    `}async function Q(){let e=document.getElementById(`plans-table-body`);e.innerHTML=`<tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Cargando...</td></tr>`;try{let{data:t}=await i(`/admin/membership-plans`);Z=t,e.innerHTML=Z.length?Z.map(nt).join(``):`<tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Sin planes todavía.</td></tr>`}catch(t){e.innerHTML=`<tr><td colspan="6" class="px-4 py-8 text-center text-red-500">${t.message}</td></tr>`}}function rt(e,t,n){e.reset(),e.elements.id.value=``,e.elements.is_active.checked=!0,t.textContent=`Nuevo plan`,n.classList.add(`hidden`)}async function it(){let e=document.getElementById(`plans-table-body`);if(!e||!await m())return;w();let t=document.getElementById(`plan-form`),n=document.getElementById(`plan-form-title`),r=document.getElementById(`plan-form-cancel`);r.addEventListener(`click`,()=>rt(t,n,r)),e.addEventListener(`click`,async e=>{let a=e.target.closest(`[data-action]`);if(!a)return;let{action:o,id:s}=a.dataset;if(o===`edit`){let e=Z.find(e=>e.id===Number(s));if(!e)return;t.elements.id.value=e.id,t.elements.name.value=e.name,t.elements.price.value=e.price,t.elements.billing_period.value=e.billing_period,t.elements.max_listings.value=e.max_listings??``,t.elements.description.value=e.description??``,t.elements.features.value=(e.features||[]).join(`
`),t.elements.is_active.checked=!!e.is_active,n.textContent=`Editar "${e.name}"`,r.classList.remove(`hidden`);return}if(o===`delete`){if(!confirm(`¿Eliminar este plan? Los emprendimientos que lo tengan quedarán sin plan.`))return;try{await i(`/admin/membership-plans/${s}`,{method:`DELETE`}),E(`Plan eliminado.`,`success`),await Q()}catch(e){E(e.message,`error`)}}}),t.addEventListener(`submit`,async e=>{e.preventDefault();let a=new FormData(t),o=a.get(`id`),s=a.get(`max_listings`),c=a.get(`features`).split(`
`).map(e=>e.trim()).filter(Boolean),l={name:a.get(`name`),price:Number(a.get(`price`)),billing_period:a.get(`billing_period`),max_listings:s?Number(s):null,description:a.get(`description`)||null,features:c,is_active:t.elements.is_active.checked};try{o?(await i(`/admin/membership-plans/${o}`,{method:`PUT`,body:JSON.stringify(l)}),E(`Plan actualizado.`,`success`)):(await i(`/admin/membership-plans`,{method:`POST`,body:JSON.stringify(l)}),E(`Plan creado.`,`success`)),rt(t,n,r),await Q()}catch(e){E(e.message,`error`)}}),await Q()}function at(e){let t=e.product;if(!t)return``;let n=x(t.title);return`
        <div class="flex items-center gap-4 p-4" data-cart-row="${e.id}">
            <img src="${t.image_url||`https://placehold.co/100x100?text=Mercova`}" alt="${n}" class="w-16 h-16 rounded-lg object-cover bg-slate-100">
            <div class="flex-1 min-w-0">
                <p class="font-medium text-slate-800 truncate">${n}</p>
                <p class="text-xs text-slate-400">${T(t.price)} c/u · ${t.transaction_type===`rent`?`Alquiler`:`Venta`}</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" data-action="decrement" data-id="${e.id}" data-qty="${e.quantity}" class="w-7 h-7 rounded border border-slate-200 text-slate-500 hover:bg-slate-50">-</button>
                <span class="w-8 text-center text-sm font-semibold">${e.quantity}</span>
                <button type="button" data-action="increment" data-id="${e.id}" data-qty="${e.quantity}" data-stock="${e.available_stock??``}" class="w-7 h-7 rounded border border-slate-200 text-slate-500 hover:bg-slate-50">+</button>
            </div>
            <div class="w-28 text-right font-semibold text-slate-800">${T(e.line_total)}</div>
            <button type="button" data-action="remove" data-id="${e.id}" class="text-red-400 hover:text-red-600 text-sm">Eliminar</button>
        </div>
    `}function ot(e){let t=document.getElementById(`cart-items`),n=document.getElementById(`checkout-section`),r=e.items||[];t.innerHTML=r.length?r.map(at).join(``):`<p class="p-10 text-slate-400 text-center">Tu carrito está vacío. <a href="/marketplace" class="text-emerald-600 font-medium hover:underline">Explora el marketplace</a>.</p>`,document.getElementById(`cart-subtotal`).textContent=T(e.subtotal),document.getElementById(`cart-total`).textContent=T(e.total),n.classList.toggle(`hidden`,r.length===0)}async function $(){let e=await h();return ot(e),await y(),e}async function st(){let e=document.getElementById(`checkout-payment-method`);if(!e)return;let{data:t}=await i(`/payment-methods`);e.innerHTML=Object.entries(t).map(([e,t])=>`<option value="${e}">${t}</option>`).join(``)}function ct(){document.getElementById(`cart-items`).addEventListener(`click`,async e=>{let t=e.target.closest(`[data-action]`);if(!t)return;let{action:n,id:r,qty:i,stock:a}=t.dataset;try{if(n===`remove`&&await v(r),n===`increment`){let e=Number(i)+1;if(a&&e>Number(a)){E(`Solo quedan ${a} unidades disponibles.`,`error`);return}await _(r,e)}if(n===`decrement`){let e=Number(i)-1;e<1?await v(r):await _(r,e)}await $()}catch(e){E(e.message,`error`)}})}function lt(){let e=document.getElementById(`checkout-form`);e?.addEventListener(`submit`,async t=>{t.preventDefault();let n=new FormData(e);try{await i(`/checkout`,{method:`POST`,body:JSON.stringify({recipient_name:n.get(`recipient_name`),recipient_phone:n.get(`recipient_phone`),address:n.get(`address`),city:n.get(`city`),department:n.get(`department`),payment_method:n.get(`payment_method`),notes:n.get(`notes`)||null})}),E(`¡Pedido realizado con éxito!`,`success`),e.reset(),await $(),window.location.href=`/panel`}catch(e){E(e.message,`error`)}})}async function ut(){if(document.getElementById(`cart-items`)&&p()){ct(),lt(),await st();try{await $()}catch{E(`No pudimos cargar tu carrito.`,`error`)}}}async function dt(){let e=document.getElementById(`favorites-grid`),t=document.getElementById(`favorites-empty`);if(e)try{let n=await oe();if(!n.length){e.innerHTML=``,t?.classList.remove(`hidden`);return}t?.classList.add(`hidden`),e.innerHTML=n.map(e=>M({...e,is_favorited:!0})).join(``)}catch(t){e.innerHTML=`<p class="col-span-full text-red-500 text-center py-10">${t.message}</p>`}}async function ft(){let e=document.getElementById(`favorites-grid`);e&&p()&&(e.addEventListener(`click`,async e=>{let t=e.target.closest(`[data-action]`);if(!t)return;e.preventDefault();let{action:n,id:r,title:i}=t.dataset;try{n===`toggle-favorite`&&(await k(r),E(`Producto eliminado de favoritos.`,`success`),await dt()),n===`add-to-cart`&&(await g(Number(r),1),await y(),E(`Producto agregado al carrito.`,`success`)),n===`order`&&P(r,i),n===`rent`&&F(r,i)}catch(e){E(e.message,`error`)}}),await dt())}function pt(){return t()?!0:(window.location.href=`/login?next=${encodeURIComponent(window.location.pathname)}`,!1)}function mt(){let e=window.location.pathname.split(`/`).filter(Boolean);return e[e.length-1]}function ht(e){let t=`★`.repeat(e.rating)+`☆`.repeat(5-e.rating);return`
        <div class="py-4 border-b border-slate-100 last:border-0">
            <div class="flex items-center justify-between">
                <p class="font-medium text-slate-800">${x(e.reviewer?.name??`Usuario`)}</p>
                <span class="text-amber-500 text-sm">${t}</span>
            </div>
            ${e.comment?`<p class="text-sm text-slate-500 mt-1">${x(e.comment)}</p>`:``}
            <p class="text-xs text-slate-400 mt-1">${new Date(e.created_at).toLocaleDateString(`es-CO`)}</p>
        </div>
    `}async function gt(e){let t=document.getElementById(`product-reviews`);if(t)try{let{data:n,meta:r}=await i(`/products/${e}/reviews`);t.innerHTML=`
            <h2 class="text-xl font-bold text-slate-900 mb-1">Reseñas</h2>
            <p class="text-sm text-slate-500 mb-4">${A(r.average||0,r.count)}</p>
            <div class="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100 px-5">
                ${n.length?n.map(ht).join(``):`<p class="text-slate-400 text-sm py-6">Todavía no hay reseñas para este producto.</p>`}
            </div>
        `}catch{t.innerHTML=``}}function _t(e){let t=document.getElementById(`product-detail`);if(!t)return;let n=e.transaction_type===`rent`,r=n?`Alquiler`:`Venta`,i=n?`${T(e.price_per_day)}/día`:T(e.price),a=!!e.is_favorited,o=x(e.title),s=x(e.description),c=x(e.condition||``),l=x(e.location||``),u=x(e.business?.name??e.seller?.name??``),d=n?`
            <button type="button" data-action="rent" class="w-full bg-indigo-600 text-white font-semibold rounded-lg py-2.5 hover:bg-indigo-700 transition">
                Solicitar alquiler
            </button>
            ${e.deposit_amount?`<p class="text-xs text-slate-400 mt-2">Incluye depósito/garantía de ${T(e.deposit_amount)}.</p>`:``}
        `:`
            <div class="flex gap-2">
                <button type="button" data-action="add-to-cart" class="flex-1 border border-emerald-600 text-emerald-700 font-semibold rounded-lg py-2.5 hover:bg-emerald-50 transition">
                    Agregar al carrito
                </button>
                <button type="button" data-action="order" class="flex-1 bg-emerald-600 text-white font-semibold rounded-lg py-2.5 hover:bg-emerald-700 transition">
                    Comprar
                </button>
            </div>
            ${e.stock==null?``:`<p class="text-xs text-slate-400 mt-2">${e.stock} unidades disponibles.</p>`}
        `;t.innerHTML=`
        <div class="aspect-square bg-slate-100 rounded-xl overflow-hidden">
            <img src="${e.image_url||`https://placehold.co/800x800?text=Mercova`}" alt="${o}" class="w-full h-full object-cover">
        </div>
        <div>
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-semibold uppercase tracking-wide text-emerald-600">${r}</span>
                <div class="flex items-center gap-3">
                    <button type="button" data-action="share" class="text-sm text-slate-400 hover:text-emerald-600">Compartir</button>
                    <button type="button" data-action="toggle-favorite" data-favorited="${a?`1`:`0`}" class="text-xl ${a?`text-red-500`:`text-slate-300`} hover:text-red-500 transition">♥</button>
                </div>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900">${o}</h1>
            <div class="mt-1">${A(Number(e.reviews_avg_rating)||0,e.reviews_count||0)}</div>
            <p class="text-3xl font-bold text-emerald-700 mt-3">${i}</p>
            <p class="text-sm text-slate-500 mt-4 whitespace-pre-line">${s}</p>

            <div class="mt-4 text-sm text-slate-500 space-y-1">
                ${c?`<p><span class="font-medium text-slate-700">Condición:</span> ${c}</p>`:``}
                ${l?`<p><span class="font-medium text-slate-700">Ubicación:</span> ${l}</p>`:``}
                <p><span class="font-medium text-slate-700">Vendido por:</span> ${u}</p>
            </div>

            <div class="mt-6">${d}</div>
        </div>
    `,t.addEventListener(`click`,async t=>{let n=t.target.closest(`[data-action]`);if(!n)return;let{action:r}=n.dataset;if(r===`share`){let t=window.location.href;navigator.share?navigator.share({title:e.title,url:t}).catch(()=>{}):(await navigator.clipboard.writeText(t),E(`Enlace copiado al portapapeles.`,`success`));return}if(pt()){if(r===`order`&&P(e.id,e.title),r===`rent`&&F(e.id,e.title),r===`add-to-cart`)try{await g(e.id,1),await y(),E(`Producto agregado al carrito.`,`success`)}catch(e){E(e.message,`error`)}if(r===`toggle-favorite`){let t=n.dataset.favorited===`1`;try{t?await k(e.id):await O(e.id),n.dataset.favorited=t?`0`:`1`,n.classList.toggle(`text-red-500`,!t),n.classList.toggle(`text-slate-300`,t)}catch(e){E(e.message,`error`)}}}})}async function vt(){let e=document.getElementById(`product-detail`);if(!e)return;let t=mt();try{let{data:e}=await i(`/products/${t}`);document.title=`${e.title} · Mercova`,_t(e),await gt(e.id)}catch(t){e.innerHTML=`<p class="col-span-full text-red-500 text-center py-20">${t.message}</p>`}}var yt={home:re,login:ie,register:ae,marketplace:fe,emprendimientos:ge,dashboard:Ie,"forgot-password":Le,"reset-password":Re,"admin-dashboard":Ve,"admin-orders":Ke,"admin-products":Ye,"admin-users":Ze,"admin-categories":tt,"admin-plans":it,cart:ut,favorites:ft,product:vt};document.addEventListener(`DOMContentLoaded`,async()=>{let e=await d();f(),await y();let t=document.body.dataset.page;e&&!t?.startsWith(`admin-`)&&w(`/notifications`);let n=yt[t];n&&n()});