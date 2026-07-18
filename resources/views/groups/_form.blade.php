@csrf

<div class="field @error('name') field-error @enderror">
    <label for="name">Name</label>
    <input id="name" type="text" name="name" value="{{ old('name', $group->name) }}" required maxlength="255">
    @error('name')
        <p class="error-message">{{ $message }}</p>
    @enderror
</div>

<div class="field @error('slug') field-error @enderror">
    <label for="slug">Slug</label>
    <p class="hint">Used in the public status page URL (kebab-case). Leave blank to derive from the name.</p>
    <input id="slug" type="text" name="slug" value="{{ old('slug', $group->slug) }}" maxlength="255"
           placeholder="client-a">
    @error('slug')
        <p class="error-message">{{ $message }}</p>
    @enderror
</div>

<div class="checkbox-row">
    <input id="is_public" type="checkbox" name="is_public" value="1" @checked(old('is_public', $group->is_public ?? true))>
    <label for="is_public">Public (a private group's status page returns 404)</label>
</div>
