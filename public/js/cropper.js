let currentCropper = null;
    let cropperResolve = null;

    function openCropperModal(imageSrc, aspectRatio) {
      return new Promise((resolve) => {
        cropperResolve = resolve;
        const modal = document.getElementById('image-cropper-modal');
        const img = document.getElementById('cropper-image');

        img.src = imageSrc;
        modal.classList.remove('hidden');
        modal.classList.add('flex');

        if (aspectRatio === 1) {
          modal.classList.add('is-circular');
        } else {
          modal.classList.remove('is-circular');
        }

        // Initialize cropper after a short delay so the modal can render
        setTimeout(() => {
          if (currentCropper) {
            currentCropper.destroy();
          }
          currentCropper = new Cropper(img, {
            aspectRatio: aspectRatio,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 0.8,
            responsive: true,
            restore: false,
            guides: false,
            center: true,
            highlight: false,
            cropBoxMovable: false,
            cropBoxResizable: false,
            toggleDragModeOnDblclick: false,
            background: false,
            wheelZoomRatio: 0.04,
          });
        }, 50);
      });
    }

    function closeCropperModal() {
      const modal = document.getElementById('image-cropper-modal');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      if (currentCropper) {
        currentCropper.destroy();
        currentCropper = null;
      }
      if (cropperResolve) {
        cropperResolve(null);
        cropperResolve = null;
      }
    }

    function applyCroppedImage() {
      if (!currentCropper) return;

      const btn = document.getElementById('apply-crop-btn');
      const originalText = btn.innerHTML;
      btn.innerHTML = '<i data-lucide="loader" class="animate-spin w-4 h-4"></i> Saving...';
      if (typeof lucide !== 'undefined') lucide.createIcons({ root: btn });
      btn.disabled = true;

      currentCropper.getCroppedCanvas({
        width: currentCropper.options.aspectRatio === 1 ? 512 : 1920,
        imageSmoothingEnabled: true,
        imageSmoothingQuality: 'high',
      }).toBlob((blob) => {
        btn.innerHTML = originalText;
        btn.disabled = false;

        const modal = document.getElementById('image-cropper-modal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');

        if (currentCropper) {
          currentCropper.destroy();
          currentCropper = null;
        }

        if (cropperResolve) {
          cropperResolve(blob);
          cropperResolve = null;
        }
      }, 'image/jpeg', 0.9);
    }