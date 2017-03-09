$(document).ready(function() {
    $('.getlink').on('click', function() {
        var keyword = $('.keyword').val();

        if (keyword.length > 0) {
            $('.loading').html('<div class="alert alert-warning"><strong>Loading...</strong> Please wait...</div>');
            document.location.href = '/index.php?keyword=' + keyword;
            setTimeout(function() {
                $('.loading').html('<div class="alert alert-success"><strong>Success!</strong> Láy danh sách SUCCESS.</div>');
                $('.keyword').val('');
            }, 3000);
        } else {
            alert('Chưa nhập tên ca sĩ.');
        }
    });
});
