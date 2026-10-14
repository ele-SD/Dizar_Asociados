document.addEventListener('DOMContentLoaded', function () {
    const perfilBoton = document.getElementById('perfilBoton');
    const perfilDropdown = document.getElementById('perfilDropdown');

    if (perfilBoton && perfilDropdown) {
        perfilBoton.addEventListener('click', function (e) {
            e.stopPropagation();
            perfilDropdown.classList.toggle('activo');
        });

        document.addEventListener('click', function () {
            perfilDropdown.classList.remove('activo');
        });
    }
});