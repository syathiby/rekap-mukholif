$(document).ready(function() {
    // Autocomplete untuk pencarian santri
    $("#santriSearch").autocomplete({
        source: function(request, response) {
            $.ajax({
                url: "../../santri/search.php",
                dataType: "json",
                data: {
                    term: request.term
                },
                success: function(data) {
                    response($.map(data, function(item) {
                        return {
                            label: item.nama + " - " + item.kelas + " - Kamar: " + item.kamar,
                            value: item.nama,
                            id: item.id,
                            kelas: item.kelas,
                            kamar: item.kamar
                        };
                    }));
                }
            });
        },
        minLength: 2,
        select: function(event, ui) {
            // Kosongkan input setelah dipilih
            $(this).val('');
            // Tambahkan ke tabel
            addSantriToTable(ui.item.id, ui.item.label.split(' - ')[0], ui.item.kelas, ui.item.kamar);
            return false;
        }
    });
    
    // Tombol tambah santri
    $("#tambahSantri").click(function() {
        var inputVal = $("#santriSearch").val();
        if (inputVal.length > 0) {
            // Trigger pencarian dan pilih yang pertama
            $("#santriSearch").autocomplete("search", inputVal);
        }
    });
    
    // Reset form
    $("#resetForm").click(function() {
        $("#daftarSantri tbody").empty();
    });
    
    // Submit form
    $("#pelanggaranForm").submit(function(e) {
        if ($("#daftarSantri tbody tr").length === 0) {
            alert("Tidak ada santri yang ditambahkan!");
            e.preventDefault();
        }
    });
});

// Fungsi untuk menambahkan santri ke tabel
function addSantriToTable(id, nama, kelas, kamar) {
    var row = '<tr class="santri-row">' +
              '<td>' + nama + '<input type="hidden" name="santri_ids[]" value="' + id + '"></td>' +
              '<td>' + kelas + '</td>' +
              '<td>' + kamar + '</td>' +
              '<td><span class="remove-btn"><i class="fas fa-times"></i> Hapus</span></td>' +
              '</tr>';
    
    $("#daftarSantri tbody").append(row);
    
    // Event untuk menghapus baris
    $("#daftarSantri").on("click", ".remove-btn", function() {
        $(this).closest("tr").remove();
    });
}