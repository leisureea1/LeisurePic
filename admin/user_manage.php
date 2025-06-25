<?php
include 'admin_header.php';
require_once __DIR__ . '/../app/error_log.php';
$usersFile = __DIR__ . '/../config/users.php';
// 重新读取用户数据，确保每次请求都是最新的
function getUsersFileArr($usersFile) {
    $users = @include $usersFile;
    if (!is_array($users)) $users = [];
    return $users;
}
$users = getUsersFileArr($usersFile);
$currentUser = $_SESSION['user'];

// 密码强度校验函数
function isStrongPassword($password) {
    // 长度至少8位，包含字母和数字
    return strlen($password) >= 8 && preg_match('/[A-Za-z]/', $password) && preg_match('/\d/', $password);
}

// 修改密码处理
$changePassStatus = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_user = trim($_POST['new_user'] ?? '');
    $new_pass = trim($_POST['new_pass'] ?? '');
    $change_pass_user = trim($_POST['change_pass_user'] ?? '');
    $change_pass_old = trim($_POST['change_pass_old'] ?? '');
    $change_pass_new = trim($_POST['change_pass_new'] ?? '');

    if ($new_user && $new_pass) {
        // 检查用户名是否已存在
        if (isset($users[$new_user])) {
            $changePassStatus = ['type' => 'danger', 'msg' => '该用户已存在，无法添加', 'reload' => false];
        } elseif (!isStrongPassword($new_pass)) {
            $changePassStatus = ['type' => 'danger', 'msg' => '密码需至少8位且包含字母和数字', 'reload' => false];
        } else {
            $users[$new_user] = password_hash($new_pass, PASSWORD_DEFAULT);
            $content = "<?php\nreturn " . var_export($users, true) . ";\n";
            file_put_contents($usersFile, $content);
            // 重新读取最新用户数据
            $users = getUsersFileArr($usersFile);
            $changePassStatus = ['type' => 'success', 'msg' => '添加用户成功！', 'reload' => false];
        }
    }
    // 修改密码时校验原密码（必须校验！）
    elseif ($change_pass_user && $change_pass_old && $change_pass_new && isset($users[$change_pass_user])) {
        if (!isset($users[$change_pass_user]) || !is_string($users[$change_pass_user]) || strlen($users[$change_pass_user]) < 10) {
            $changePassStatus = ['type' => 'danger', 'msg' => '用户密码数据异常'];
        } elseif ($change_pass_user === $currentUser && !password_verify($change_pass_old, $users[$change_pass_user])) {
            // 当前登录用户必须校验旧密码
            $changePassStatus = ['type' => 'danger', 'msg' => '原密码错误'];
        } elseif (!isStrongPassword($change_pass_new)) {
            $changePassStatus = ['type' => 'danger', 'msg' => '新密码需至少8位且包含字母和数字'];
        } else {
            $users[$change_pass_user] = password_hash($change_pass_new, PASSWORD_DEFAULT);
            $content = "<?php\nreturn " . var_export($users, true) . ";\n";
            file_put_contents($usersFile, $content);
            $changePassStatus = ['type' => 'success', 'msg' => '密码修改成功！', 'reload' => true];
        }
    }
    elseif (isset($_POST['del_user'])) {
        if ($_POST['del_user'] === $currentUser) {
            $changePassStatus = ['type' => 'danger', 'msg' => '不能删除当前登录用户'];
        } else {
            unset($users[$_POST['del_user']]);
            $content = "<?php\nreturn " . var_export($users, true) . ";\n";
            file_put_contents($usersFile, $content);
            // 重新读取最新用户数据
            $users = getUsersFileArr($usersFile);
            $changePassStatus = ['type' => 'success', 'msg' => '删除成功！', 'reload' => false];
        }
    }
}
?>
<div class="container-fluid">
    <div class="card">
        <div class="card-header">仅上传用户管理</div>
        <div class="card-body">
            <div id="msgBox">
            <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                <div class="alert alert-success">操作成功！</div>
            <?php elseif (isset($_GET['success']) && $_GET['success'] == 2): ?>
                <div class="alert alert-success">密码修改成功！</div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger" id="errorMsg"><?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>
            <?php if ($changePassStatus): ?>
                <div class="alert alert-<?php echo $changePassStatus['type']; ?>" id="changePassMsg">
                    <?php echo htmlspecialchars($changePassStatus['msg']); ?>
                </div>
                <script>
                setTimeout(function(){
                    var msg = document.getElementById('changePassMsg');
                    if(msg) msg.style.display = 'none';
                    <?php if (!empty($changePassStatus['reload'])): ?>
                        location.href='user_manage.php';
                    <?php endif; ?>
                }, 1500);
                </script>
            <?php endif; ?>
            </div>
            <form method="post" class="mb-4" onsubmit="return checkNewPassStrength(this.new_pass);">
                <div class="form-group">
                    <label>新用户名</label>
                    <input name="new_user" class="form-control" autocomplete="off">
                </div>
                <div class="form-group mt-2">
                    <label>新密码</label>
                    <input name="new_pass" type="password" class="form-control" autocomplete="new-password">
                    <small class="text-muted">密码需至少8位且包含字母和数字</small>
                </div>
                <button type="submit" class="btn btn-primary mt-2">添加用户</button>
            </form>
            <!-- 修改密码表单 -->
            <form method="post" class="mb-4" onsubmit="return checkNewPassStrength(this.change_pass_new);">
                <div class="form-group">
                    <label>选择用户</label>
                    <select name="change_pass_user" class="form-control" required>
                        <option value="">请选择用户</option>
                        <?php foreach ($users as $u => $h): ?>
                            <option value="<?php echo htmlspecialchars($u); ?>"><?php echo htmlspecialchars($u); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mt-2">
                    <label>原密码</label>
                    <input name="change_pass_old" type="password" class="form-control" autocomplete="current-password" required>
                </div>
                <div class="form-group mt-2">
                    <label>新密码</label>
                    <input name="change_pass_new" type="password" class="form-control" autocomplete="new-password" required>
                    <small class="text-muted">密码需至少8位且包含字母和数字</small>
                </div>
                <button type="submit" class="btn btn-warning mt-2">修改密码</button>
            </form>
            <div class="card">
                <div class="card-header">用户列表</div>
                <div class="card-body p-0">
                    <?php if ($users): ?>
                    <table class="table table-hover mb-0">
                        <thead><tr><th>用户名</th><th>操作</th></tr></thead>
                        <tbody>
                        <?php foreach ($users as $u => $h): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($u); ?></td>
                            <td>
                                <form method="post" style="display:inline;" onsubmit="return checkDeleteUser('<?php echo htmlspecialchars($u); ?>');">
                                    <input type="hidden" name="del_user" value="<?php echo htmlspecialchars($u); ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"
                                    <?php if ($u === $currentUser) echo 'disabled'; ?>>删除</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <div class="text-muted p-3">暂无用户</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function checkDeleteUser(username) {
    var currentUser = <?php echo json_encode($currentUser); ?>;
    if (username === currentUser) {
        alert('不能删除当前登录用户');
        return false;
    }
    return confirm('确定删除该用户？');
}
function checkNewPassStrength(input) {
    var val = input.value;
    if (val.length < 8 || !/[A-Za-z]/.test(val) || !/\d/.test(val)) {
        alert('密码需至少8位且包含字母和数字');
        input.focus();
        return false;
    }
    return true;
}
window.onload = function() {
    var err = document.getElementById('errorMsg');
    if (err) setTimeout(function(){ err.style.display='none'; }, 3000);
};
</script>
<?php include 'admin_footer.php'; ?>
