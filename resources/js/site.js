import Alpine from '@alpinejs/csp'
import Masonry from 'masonry-layout'
import PhotoSwipeLightbox from 'photoswipe/lightbox'
import 'photoswipe/style.css'

window.Alpine = Alpine

// ─── Composant galerie (show) ─────────────────────────────────────────────────
Alpine.data('gallery', (gallerySlug) => ({
    slug: gallerySlug,
    selected: [],

    toggle(assetId) {
        const idx = this.selected.indexOf(assetId)
        idx >= 0 ? this.selected.splice(idx, 1) : this.selected.push(assetId)
    },

    isSelected(assetId) {
        return this.selected.includes(assetId)
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

// ─── Masonry ──────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const grid = document.querySelector('[data-masonry-grid]')
    if (grid) {
        new Masonry(grid, {
            itemSelector: '[data-masonry-item]',
            columnWidth: '[data-masonry-sizer]',
            percentPosition: true,
            gutter: 8,
            transitionDuration: 0,
        })
    }

    // ─── PhotoSwipe ───────────────────────────────────────────────────────────
    const pswpGallery = document.querySelector('#pswp-gallery')
    if (! pswpGallery) return

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
})
