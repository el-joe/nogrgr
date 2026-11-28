/**
 * Shared variant picker (x-sf::variant-picker; #eco-variant-modal for Ecommet, #sf-variant-modal for Elora): a card cart button
 * with [data-variant-picker] loads the product from the Store API (routes.product,
 * products.show) and lets the shopper pick the options. Each pick re-matches the variant
 * (variant.options = {attributeId: optionId}), greys out options that cannot complete
 * an in-stock variant, and updates image, price, compare, discount, stock and
 * the quantity list. "Add to cart" is a normal [data-add-to-cart] inside
 * [data-cart-scope], so catalog/cart.js + the motion morph / fly-to-cart / mini-cart
 * popup handle it; the sheet closes once the cart confirms. Loading / error states
 * with retry and a product-page link.
 */
import { http } from '../../core/http';
import { context } from './context';
import { closeSheet, openSheet } from '../core/motion/sheet';

const MAX_QTY = 10;
const cache = new Map();
let skin = {};
let modal = null;
let texts = {};
let current = null;
let requestToken = 0;

const $ = (selector) => modal.querySelector(selector);
const $$ = (selector) => [...modal.querySelectorAll(selector)];
const priceText = (price) => price?.display?.formatted ?? price?.formatted ?? '';

function showState(name) {
    $$('[data-variant-state]').forEach((node) => {
        node.hidden = node.dataset.variantState !== name;
    });
}

function productUrl(id) {
    const template = context().routes.product;

    return template ? template.replace(/\/0(?=$|\?)/, `/${id}`) : null;
}

function setDetails(url) {
    $$('[data-variant-details]').forEach((link) => {
        if (url) {
            link.href = url;
        }
    });
}

function matches(variant, selection) {
    return Object.entries(selection).every(([attribute, option]) => !option || Number(variant.options?.[attribute]) === option);
}

function selectedVariant() {
    const { product, selection } = current;
    const groups = product.attributes ?? [];

    if (groups.some((group) => !selection[group.id])) {
        return null;
    }

    return product.variants.find((variant) => matches(variant, selection)) ?? null;
}

function renderGroups() {
    const holder = $('[data-variant-attributes]');
    const template = modal.querySelector('template[data-variant-group]');
    holder.replaceChildren();

    (current.product.attributes ?? []).forEach((attribute) => {
        const node = template.content.firstElementChild.cloneNode(true);
        node.dataset.attribute = String(attribute.id);
        node.querySelector('[data-group-name]').textContent = attribute.name;
        const options = node.querySelector('[data-group-options]');
        options.setAttribute('aria-label', attribute.name);

        attribute.options.forEach((option) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.setAttribute('role', 'radio');
            button.dataset.option = String(option.id);

            if (attribute.swatch && option.swatch) {
                button.className = skin.swatch;
                button.style.setProperty('--swatch', option.swatch);
                button.setAttribute('aria-label', option.name);
                button.title = option.name;
            } else {
                button.className = skin.option;
                button.textContent = option.name;
            }

            button.addEventListener('click', () => {
                current.selection[attribute.id] = option.id;
                update();
            });
            options.appendChild(button);
        });

        /* Arrow keys move inside a radio group (WAI-ARIA radio pattern). */
        options.addEventListener('keydown', (event) => {
            const step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[event.key];

            if (!step) {
                return;
            }

            const buttons = [...options.querySelectorAll('[data-option]')];
            const index = buttons.indexOf(document.activeElement);
            const horizontal = event.key === 'ArrowLeft' || event.key === 'ArrowRight';
            const direction = horizontal && document.documentElement.dir === 'rtl' ? -step : step;
            const next = buttons[(index + direction + buttons.length) % buttons.length];
            event.preventDefault();
            next?.focus();
            next?.click();
        });

        holder.appendChild(node);
    });

    renderTiles(holder);
}

/** One image tile per real combination (own image, "Black / M" label); the attribute buttons stay as the data model. */
function renderTiles(holder) {
    const { product } = current;

    if (product.variants.length < 2 || !(product.attributes ?? []).length) {
        return;
    }

    const track = document.createElement('div');
    track.className = skin.tiles;
    track.setAttribute('role', 'radiogroup');
    track.dataset.variantTiles = '';

    product.variants.forEach((variant) => {
        const label = product.attributes.map((attribute) => attribute.options.find((option) => option.id === variant.options?.[attribute.id])?.name).filter(Boolean).join(' / ') || variant.label || '';
        const tile = document.createElement('button');
        tile.type = 'button';
        tile.setAttribute('role', 'radio');
        tile.className = skin.tile;
        tile.dataset.variantTile = String(variant.id);
        tile.title = label;
        tile.setAttribute('aria-label', variant.in_stock ? label : label + ' - ' + texts.out);
        tile.toggleAttribute('data-unavailable', !variant.in_stock);

        if (variant.image) {
            const img = document.createElement('img');
            img.src = variant.image;
            img.alt = '';
            img.width = 64;
            img.height = 64;
            img.loading = 'lazy';
            tile.appendChild(img);
        } else {
            const text = document.createElement('span');
            text.className = skin.tileText;
            text.textContent = label;
            tile.appendChild(text);
        }

        if (!variant.in_stock) {
            tile.setAttribute('aria-disabled', 'true');
            tile.disabled = true;
            tile.title = label + ' - ' + texts.out;
        }

        tile.addEventListener('click', () => {
            if (!variant.in_stock) {
                return;
            }

            current.selection = Object.fromEntries(Object.entries(variant.options ?? {}).map(([key, value]) => [key, Number(value)]));
            update();
        });
        track.appendChild(tile);
    });

    holder.querySelectorAll('[data-group-options]').forEach((options) => options.setAttribute('hidden', ''));
    holder.appendChild(track);
}


function update() {
    const { product, selection } = current;
    const variant = selectedVariant();

    /* Options: checked state, current value label, and "no variant possible" marking. */
    $$('[data-attribute]').forEach((group) => {
        const attributeId = Number(group.dataset.attribute);
        const attribute = product.attributes.find((item) => item.id === attributeId);
        const chosen = selection[attributeId];
        group.querySelector('[data-group-value]').textContent = attribute.options.find((option) => option.id === chosen)?.name ?? texts.choose;

        group.querySelectorAll('[data-option]').forEach((button) => {
            const optionId = Number(button.dataset.option);
            const trial = { ...selection, [attributeId]: optionId };
            const possible = product.variants.some((item) => item.in_stock && matches(item, trial));
            button.setAttribute('aria-checked', String(chosen === optionId));
            button.tabIndex = chosen === optionId || (!chosen && button === group.querySelector('[data-option]')) ? 0 : -1;
            button.toggleAttribute('data-unavailable', !possible);
        });
    });

    document.querySelectorAll('[data-variant-tile]').forEach((tile) => tile.setAttribute('aria-checked', String(Boolean(variant) && Number(tile.dataset.variantTile) === variant.id)));

    const price = variant?.price ?? product.price;
    const compare = variant ? variant.compare_price : product.compare_price;
    const discount = variant ? variant.discount_percent : product.discount_percent;
    const image = variant?.image || product.images?.[0] || '';
    const inStock = variant ? variant.in_stock : false;
    const stock = variant?.stock;

    const img = $('[data-variant-image]');

    if (image && img.getAttribute('src') !== image) {
        img.src = image;
    }

    img.alt = product.name;
    $('[data-variant-price]').textContent = priceText(price);
    const compareNode = $('[data-variant-compare]');
    compareNode.hidden = !compare;
    compareNode.textContent = priceText(compare);
    const discountNode = $('[data-variant-discount]');
    discountNode.hidden = !discount;
    discountNode.textContent = discount ? `-${discount}%` : '';

    const stockNode = $('[data-variant-stock]');
    stockNode.classList.toggle(skin.red, Boolean(variant) && (!inStock || (stock !== null && stock <= 5)));
    stockNode.classList.toggle(skin.green, Boolean(variant) && inStock && !(stock !== null && stock <= 5));
    stockNode.classList.toggle(skin.gray, !variant);
    stockNode.textContent = !variant
        ? texts.choose
        : !inStock
            ? texts.out
            : stock !== null && stock <= 5
                ? texts.left.replace('__COUNT__', String(stock))
                : texts.in_stock;

    /* Quantity: 1..min(stock, 10), keeping the chosen value when still allowed. */
    const qty = $('[data-qty]');
    const limit = Math.max(1, Math.min(MAX_QTY, stock ?? MAX_QTY));
    const keep = Math.min(Number(qty.value) || 1, limit);
    qty.replaceChildren(...Array.from({ length: limit }, (_, index) => new Option(String(index + 1), String(index + 1))));
    qty.value = String(keep);
    qty.disabled = !inStock;

    const add = $('[data-add-to-cart]');
    add.dataset.productId = String(product.id);
    add.dataset.variantId = variant ? String(variant.id) : '';
    add.disabled = !variant || !inStock;
    add.querySelector('[data-cart-label]').textContent = !variant ? texts.choose : inStock ? texts.add : texts.out;

    setDetails(variant ? `${product.url}${product.url.includes('?') ? '&' : '?'}variant=${variant.id}` : product.url);
}

async function load(id, fallbackUrl) {
    const token = ++requestToken;
    setDetails(fallbackUrl);
    showState('loading');

    try {
        let product = cache.get(id);

        if (!product) {
            const url = productUrl(id);

            if (!url) {
                throw new Error('product endpoint unavailable');
            }

            const response = await http.get(url, null, { toast: false, silent: true });
            product = response?.data;

            if (!product?.id) {
                throw new Error('empty product');
            }

            product.variants ??= [];
            product.attributes ??= [];
            cache.set(id, product);
        }

        if (token !== requestToken) {
            return;
        }

        /* Explicit choice: nothing is pre-selected, "Add to cart" stays disabled until one in-stock combination is picked. */
        current = { product, selection: {} };

        $('[data-variant-name]').textContent = product.name;
        $('[data-variant-attributes]').scrollTop = 0;
        $('[data-qty]').value = '1';
        renderGroups();
        update();
        showState('ready');
        $('[data-add-to-cart]').dataset.state = 'idle';
        requestAnimationFrame(() => (modal.querySelector('[data-variant-tile]:not([aria-disabled])') ?? modal.querySelector('[data-option]'))?.focus({ preventScroll: true }));
    } catch {
        if (token === requestToken) {
            showState('error');
        }
    }
}

let bound = false;

export function initVariantPicker() {
    modal = document.querySelector('[data-variant-modal]');

    if (!modal || bound) {
        return;
    }

    bound = true;
    skin = {
        swatch: 'eco-swatch bg-[var(--swatch)]',
        option: 'eco-option',
        tiles: 'flex max-h-[168px] flex-wrap gap-2 overflow-y-auto p-0.5',
        tile: 'eco-variant-tile',
        tileText: 'eco-variant-tile-text',
        red: 'text-eco-red',
        green: 'text-eco-green',
        gray: 'text-eco-gray',
        ...JSON.parse(modal.dataset.variantSkin || '{}'),
    };

    try {
        texts = JSON.parse(modal.querySelector('[data-variant-texts]')?.textContent || '{}');
    } catch {
        texts = {};
    }

    let lastId = null;
    let lastUrl = null;

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-variant-picker]');

        if (!trigger || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.button > 0) {
            return;
        }

        event.preventDefault();
        lastId = Number(trigger.dataset.productId);
        lastUrl = trigger.getAttribute('href');
        openSheet(modal);
        load(lastId, lastUrl);
    });

    modal.querySelector('[data-variant-retry]')?.addEventListener('click', () => {
        if (lastId) {
            cache.delete(lastId);
            load(lastId, lastUrl);
        }
    });

    /* Close shortly after the cart confirms so the button morph + fly-to-cart can play. */
    document.addEventListener('sf:cart-updated', (event) => {
        if (event.detail?.source && modal.contains(event.detail.source)) {
            window.setTimeout(() => closeSheet(modal), 650);
        }
    });
}
