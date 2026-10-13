const typeField = document.getElementById('catalog-type');

if (typeField) {
    const fileField = document.getElementById('digital-file-field');
    const fileInput = document.getElementById('digital-file');
    const copiesField = document.getElementById('digital-copies-field');
    const collectionTypeField = document.getElementById('catalog-collection-type');
    const accessModeField = document.getElementById('catalog-access-mode');

    const updateDigitalFields = () => {
        const isDigital = typeField.value === 'digital';
        fileField.style.display = isDigital ? '' : 'none';
        fileInput.disabled = !isDigital;
        copiesField.style.display = isDigital ? '' : 'none';
        if (!isDigital) {
            fileInput.value = '';
        }
        const isReadingRoomOnly = collectionTypeField.value !== 'book' || isDigital;
        accessModeField.value = isReadingRoomOnly ? 'reading_room' : accessModeField.value;
        accessModeField.title = isReadingRoomOnly ? 'Koleksi ini hanya dapat dibaca di perpustakaan.' : '';
    };

    typeField.addEventListener('change', updateDigitalFields);
    collectionTypeField.addEventListener('change', updateDigitalFields);
    updateDigitalFields();
}
