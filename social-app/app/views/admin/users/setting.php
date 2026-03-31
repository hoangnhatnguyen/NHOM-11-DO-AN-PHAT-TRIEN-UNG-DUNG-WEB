<div class="container">
<div class="card p-4 rounded-4 shadow-sm">

<h5 class="mb-3">Quyền riêng tư</h5>

<div class="mb-3 d-flex justify-content-between align-items-center">
<span>Ai có thể theo dõi bạn</span>
<select class="form-select w-auto"
        onchange="updatePrivacy('privacy_follow',this.value)">
    <option value="everyone">Mọi người</option>
    <option value="mutual">Bạn chung</option>
</select>
</div>

<div class="mb-4 d-flex justify-content-between align-items-center">
<span>Ai có thể bình luận bài viết</span>
<select class="form-select w-auto"
        onchange="updatePrivacy('privacy_comment',this.value)">
    <option value="everyone">Mọi người</option>
    <option value="mutual">Bạn chung</option>
</select>
</div>

<hr>

<h5>Danh sách chặn</h5>

<div id="blockedList">
<?php foreach($blocked as $u): ?>
<div class="d-flex justify-content-between border p-2 mb-2">
    <span><?= $u['username'] ?></span>
    <button class="btn btn-sm btn-danger"
            onclick="unblock(<?= $u['id'] ?>)">
        Hủy chặn
    </button>
</div>
<?php endforeach; ?>
</div>

</div>
</div>

<script>

function updatePrivacy(type,value){
fetch('/setting-api/update-privacy',{
method:'POST',
headers:{'Content-Type':'application/x-www-form-urlencoded'},
body:type+'='+value
});
}

function unblock(id){
fetch('/setting-api/unblock',{
method:'POST',
headers:{'Content-Type':'application/x-www-form-urlencoded'},
body:'id='+id
}).then(()=>location.reload());
}

</script>