# 🛠️ 部署教程

> 推荐环境：宝塔面板 + PHP 7.4 及以上，无需数据库

---

## 环境要求

- **操作系统**：Linux/Windows/macOS（推荐宝塔面板）
- **PHP**：7.4 及以上
- **无需数据库**
- **推荐扩展**：`fileinfo`、`iconv`、`zip`、`mbstring`、`openssl`、`exif`
- **Web服务器**：Nginx/Apache

---

## 快速部署（以宝塔为例）

1. **上传源码**
   - 通过宝塔面板或 FTP 上传本项目所有文件到网站根目录。

2. **设置运行环境**
   - 在宝塔添加网站，选择 PHP 7.4 或更高版本。
   - 安装并启用上述 PHP 扩展。

3. **目录权限**
   - 确保 `public/uploads`、`log`、`config` 目录有写入权限（755 或 777，视服务器安全策略）。

4. **首次访问自动安装**
   - 浏览器访问你的域名，会自动跳转到 `/install` 目录进行初始化配置。
   - 按提示填写站点信息和管理员账号，完成后请**删除 `install` 目录**。

5. **访问前台与后台**
   - 前台入口：`http://你的域名/`
   - 后台入口：`http://你的域名/admin/`，使用管理员账号登录。

---

## Nginx 伪静态（可选）

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
location ~ \.php$ {
    fastcgi_pass unix:/tmp/php-cgi.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```

---

## 常见问题

- 如遇上传失败，请检查 PHP 上传限制（`php.ini` 的 `upload_max_filesize` 和 `post_max_size`）。
- 如遇权限问题，请检查相关目录权限。
- 如需 HTTPS，请在宝塔面板中配置 SSL。

> 本项目无需数据库，所有数据均存储于本地文件。如需升级，直接覆盖文件并保留 `config`、`log`、`public/uploads` 目录即可。
