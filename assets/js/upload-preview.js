class UploadPreview {
    constructor(formId, inputId, sectionId, gridId, countId) {
        this.form = document.getElementById(formId);
        this.input = document.getElementById(inputId);
        this.section = document.getElementById(sectionId);
        this.grid = document.getElementById(gridId);
        this.count = document.getElementById(countId);
        this.objectUrls = [];

        if (!this.form || !this.input || !this.section || !this.grid || !this.count) {
            return;
        }

        this.maxPhotos = Number(this.form.dataset.maxPhotos || 10);
        this.maxPreviewPhotos = Number(this.form.dataset.maxPreviewPhotos || 6);

        this.input.addEventListener('change', () => this.render());
    }

    clearObjectUrls() {
        this.objectUrls.forEach((url) => URL.revokeObjectURL(url));
        this.objectUrls = [];
    }

    clearPreview() {
        this.clearObjectUrls();
        this.grid.innerHTML = '';
        this.section.classList.remove('is-visible');
        this.count.textContent = 'No photos selected yet.';
    }

    render() {
        const files = Array.from(this.input.files || []);

        if (files.length === 0) {
            this.clearPreview();
            return;
        }

        this.clearObjectUrls();
        this.grid.innerHTML = '';
        this.section.classList.add('is-visible');

        const acceptedFiles = files.slice(0, this.maxPhotos);
        const previewFiles = acceptedFiles.slice(0, this.maxPreviewPhotos);

        this.count.textContent = this.buildCountText(files.length, acceptedFiles.length);

        previewFiles.forEach((file, index) => {
            const tile = this.createTile(file);

            const isLastPreview = index === this.maxPreviewPhotos - 1;
            const hasHiddenFiles = acceptedFiles.length > this.maxPreviewPhotos;

            if (isLastPreview && hasHiddenFiles) {
                const hiddenCount = acceptedFiles.length - this.maxPreviewPhotos;
                tile.classList.add('has-more');
                tile.dataset.moreLabel = `+${hiddenCount} more`;
            }

            this.grid.appendChild(tile);
        });
    }

    buildCountText(originalCount, acceptedCount) {
        if (originalCount > acceptedCount) {
            return `${acceptedCount} photos selected. ${originalCount - acceptedCount} extra photo(s) will be ignored.`;
        }

        return `${acceptedCount} photo(s) selected.`;
    }

    createTile(file) {
        const tile = document.createElement('div');
        tile.className = 'preview-tile';

        const image = document.createElement('img');
        const objectUrl = URL.createObjectURL(file);

        this.objectUrls.push(objectUrl);

        image.src = objectUrl;
        image.alt = file.name;

        tile.appendChild(image);

        return tile;
    }
}

class EventLinkUpdater {
    constructor(orgInputId, eventInputId) {
        this.orgInput = document.getElementById(orgInputId);
        this.eventInput = document.getElementById(eventInputId);

        if (!this.orgInput || !this.eventInput) {
            return;
        }

        this.orgInput.addEventListener("change", () => this.update());
        this.eventInput.addEventListener("change", () => this.update());
    }

    update() {
        const orgId = this.orgInput.value;
        const eventId = this.eventInput.value;

        if (!orgId || !eventId) {
            return;
        }

        window.location.href = `main.php?org_id=${encodeURIComponent(orgId)}&event_id=${encodeURIComponent(eventId)}`;
    }
}

new UploadPreview(
    'uploadForm',
    'photos',
    'previewSection',
    'previewGrid',
    'selectedCount'
);

new EventLinkUpdater("org_id", "event_id");
