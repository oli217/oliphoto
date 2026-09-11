import Alpine from '@alpinejs/csp'
import Masonry from 'masonry-layout'
import PhotoSwipeLightbox from 'photoswipe/lightbox'
import 'photoswipe/style.css'

window.Alpine = Alpine

// ─── Composant galerie (show) ─────────────────────────────────────────────────
Alpine.data('gallery', (gallerySlug) => ({
    slug: gallerySlug,
    selected: [],
    page: 0,
    loading: false,
    hasMore: true,
    msnry: null,

    init() {
        const grid     = document.getElementById('pswp-gallery')
        const sentinel = document.getElementById('gallery-sentinel')

        // Masonry
        this.msnry = new Masonry(grid, {
            itemSelector: '[data-masonry-item]',
            columnWidth: '[data-masonry-sizer]',
            percentPosition: true,
            gutter: 8,
            transitionDuration: 0,
        })

        // PhotoSwipe
        const lightbox = new PhotoSwipeLightbox({
            gallery: '#pswp-gallery',
            children: '[data-pswp-src]',
            pswpModule: () => import('photoswipe'),
            showHideAnimationType: 'zoom',
            bgOpacity: 0.92,
            padding: { top: 20, bottom: 60, left: 0, right: 0 },
        })

        // Caption EXIF sous la photo
        lightbox.on('uiRegister', function () {
            lightbox.pswp.ui.registerElement({
                name: 'exif-caption',
                order: 9,
                isButton: false,
                appendTo: 'root',
                onInit: (el, pswp) => {
                    el.className = 'pswp__exif-caption'

                    pswp.on('change', () => {
                        const anchor = pswp.currSlide?.data?.element
                        if (! anchor) { el.innerHTML = ''; return }

                        const d = anchor.dataset
                        const techLine = [d.exifFocal, d.exifAperture, d.exifShutter, d.exifIso]
                            .filter(Boolean).join(' · ')

                        const parts = [
                            d.exifCamera && `<strong>${d.exifCamera}</strong>`,
                            d.exifLens,
                            techLine,
                            d.exifDate,
                        ].filter(Boolean)

                        el.innerHTML = parts.length
                            ? `<div class="pswp__exif-inner">${parts.join('<span class="pswp__exif-sep">·</span>')}</div>`
                            : ''
                    })
                },
            })
        })

        lightbox.init()

        // Event delegation pour sélection (items chargés dynamiquement)
        grid.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-select-id]')
            if (btn) { e.stopPropagation(); this.toggle(btn.dataset.selectId) }
        })

        // Mise à jour de la classe is-selected sur changement de sélection
        this.$watch('selected', (sel) => {
            document.querySelectorAll('[data-masonry-item]').forEach(item => {
                const id = item.dataset.assetId
                if (id) item.classList.toggle('is-selected', sel.includes(id))
            })
        })

        // IntersectionObserver pour infinite scroll
        new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && !this.loading && this.hasMore) {
                this.loadMore()
            }
        }, { rootMargin: '300px' }).observe(sentinel)

        // Première page
        this.loadMore()
    },

    async loadMore() {
        if (this.loading || !this.hasMore) return
        this.loading = true

        try {
            const res  = await fetch(`/api/galleries/${this.slug}/photos?page=${this.page + 1}`)

            if (! res.ok) throw new Error(`HTTP ${res.status}`)

            const data = await res.json()

            const grid     = document.getElementById('pswp-gallery')
            const sentinel = document.getElementById('gallery-sentinel')

            const items = data.photos.map(p => {
                const tmp = document.createElement('div')
                tmp.innerHTML = this.photoHTML(p)
                return tmp.firstElementChild
            })

            items.forEach(item => grid.insertBefore(item, sentinel))
            this.msnry.appended(items)

            this.page    = data.current_page
            this.hasMore = data.has_more
        } catch (err) {
            console.error('Erreur chargement photos:', err)
            this.hasMore = false
        } finally {
            this.loading = false
        }
    },

    photoHTML(p) {
        const esc = s => s ? String(s).replace(/"/g, '&quot;') : ''
        return `<div data-masonry-item class="gallery-item" data-asset-id="${esc(p.id)}">
            <a data-pswp-src="${esc(p.url)}"
               data-pswp-width="${p.width ?? 4000}"
               data-pswp-height="${p.height ?? 3000}"
               data-exif-camera="${esc(p.exif_camera)}"
               data-exif-lens="${esc(p.exif_lens)}"
               data-exif-focal="${esc(p.exif_focal)}"
               data-exif-aperture="${esc(p.exif_aperture)}"
               data-exif-shutter="${esc(p.exif_shutter)}"
               data-exif-iso="${esc(p.exif_iso)}"
               data-exif-date="${esc(p.exif_date)}"
               aria-label="Voir en grand">
                <picture>
                    <source srcset="${esc(p.avif_url)}" type="image/avif">
                    <source srcset="${esc(p.webp_url)}" type="image/webp">
                    <img src="${esc(p.url)}" loading="lazy" class="w-full h-auto block"
                         width="${p.width ?? ''}" height="${p.height ?? ''}">
                </picture>
            </a>
            <button class="select-btn" data-select-id="${esc(p.id)}" aria-label="Sélectionner">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                </svg>
            </button>
        </div>`
    },

    toggle(id) {
        const i = this.selected.indexOf(id)
        i >= 0 ? this.selected.splice(i, 1) : this.selected.push(id)
    },

    isSelected(id) {
        return this.selected.includes(id)
    },

    selectAll() {
        this.selected = Array.from(
            document.querySelectorAll('[data-asset-id]'),
            el => el.dataset.assetId
        )
    },

    clearSelection() {
        this.selected = []
    },

    downloadZip() {
        const form = document.createElement('form')
        form.method  = 'POST'
        form.action  = '/api/zip'

        const csrf = document.createElement('input')
        csrf.name  = '_token'
        csrf.value = document.querySelector('meta[name="csrf-token"]')?.content ?? ''
        form.appendChild(csrf)

        const galleryInput  = document.createElement('input')
        galleryInput.name   = 'gallery'
        galleryInput.value  = this.slug
        form.appendChild(galleryInput)

        this.selected.forEach(id => {
            const input = document.createElement('input')
            input.name  = 'assets[]'
            input.value = id
            form.appendChild(input)
        })

        document.body.appendChild(form)
        form.submit()
        document.body.removeChild(form)
    },
}))

// ─── Filtre galeries (index) ──────────────────────────────────────────────────
Alpine.data('galleryFilter', () => ({
    active: '',

    setFilter(slug) {
        this.active = slug
    },

    matches(categoriesAttr) {
        if (!this.active) return true
        return categoriesAttr.split(' ').includes(this.active)
    },
}))

Alpine.start()
