# ❓ 常见问题

<details>
<summary><b>1. 启动或访问时报错怎么办？</b></summary>

- 检查 PHP 版本是否为 7.4 及以上。
- 检查 PHP 扩展是否齐全：`fileinfo`、`iconv`、`zip`、`mbstring`、`openssl`、`exif`。
- 检查 `public/uploads`、`log`、`config` 目录权限，确保可写。
- 查看 `log/error.log` 获取详细错误信息。

</details>

<details>
<summary><b>2. 上传图片失败怎么办？</b></summary>

- 检查 PHP 上传限制（`php.ini` 的 `upload_max_filesize` 和 `post_max_size`）。
- 检查图片格式和大小是否符合设置要求。
- 检查是否达到每日上传次数限制。

</details>

<details>
<summary><b>3. 安装后如何保证安全？</b></summary>

- 安装完成后务必删除 `install` 目录。
- 定期备份 `config`、`log`、`public/uploads` 目录。

</details>

<details>
<summary><b>4. 其他问题</b></summary>

- 可在 [GitHub 项目](https://github.com/leisureea1/LeisurePic/issues) 提交 issue，或查阅官方文档。

</details>
