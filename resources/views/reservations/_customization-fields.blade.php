@php($formSuffix = $formSuffix ?? 'reservation')
<details class="customization-request border rounded p-3">
    <summary class="fw-semibold">Request a custom size or design (optional)</summary>
    <div class="pt-3 vstack gap-3">
        <label class="form-check mb-0">
            <input class="form-check-input" type="checkbox" name="customization_requested" value="1" @checked(old('customization_requested'))>
            <span class="form-check-label">Ask the shop to make a custom variation of this furniture.</span>
        </label>
        <p class="small text-muted mb-0">Describe the changes you want. This sends a request for shop review; it does not confirm price, production, or availability.</p>
        <div class="row g-2">
            <div class="col-4"><label class="form-label small" for="custom-width-cm-{{ $formSuffix }}">Width (cm)</label><input class="form-control form-control-sm" id="custom-width-cm-{{ $formSuffix }}" name="custom_width_cm" type="number" min="1" max="500" step="0.1" value="{{ old('custom_width_cm') }}"></div>
            <div class="col-4"><label class="form-label small" for="custom-depth-cm-{{ $formSuffix }}">Depth (cm)</label><input class="form-control form-control-sm" id="custom-depth-cm-{{ $formSuffix }}" name="custom_depth_cm" type="number" min="1" max="500" step="0.1" value="{{ old('custom_depth_cm') }}"></div>
            <div class="col-4"><label class="form-label small" for="custom-height-cm-{{ $formSuffix }}">Height (cm)</label><input class="form-control form-control-sm" id="custom-height-cm-{{ $formSuffix }}" name="custom_height_cm" type="number" min="1" max="500" step="0.1" value="{{ old('custom_height_cm') }}"></div>
            <div class="col-md-6"><label class="form-label small" for="custom-wood-type-{{ $formSuffix }}">Preferred wood</label><input class="form-control form-control-sm" id="custom-wood-type-{{ $formSuffix }}" name="custom_wood_type" maxlength="80" value="{{ old('custom_wood_type') }}" placeholder="e.g. Gmelina or Mahogany"></div>
            <div class="col-md-6"><label class="form-label small" for="custom-varnish-finish-{{ $formSuffix }}">Preferred varnish / finish</label><input class="form-control form-control-sm" id="custom-varnish-finish-{{ $formSuffix }}" name="custom_varnish_finish" maxlength="80" value="{{ old('custom_varnish_finish') }}" placeholder="e.g. Narra Look"></div>
        </div>
        <div><label class="form-label small" for="custom-design-notes-{{ $formSuffix }}">Design and customization details</label><textarea class="form-control form-control-sm" id="custom-design-notes-{{ $formSuffix }}" name="custom_design_notes" rows="3" maxlength="1500" placeholder="Describe the design, changes, or other requirements.">{{ old('custom_design_notes') }}</textarea></div>
    </div>
</details>