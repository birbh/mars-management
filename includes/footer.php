<?php
$close_content_wrapper = $close_content_wrapper ?? true;
$custom_footer_html = $custom_footer_html ?? '';

if ($custom_footer_html === '') {
    $custom_footer_html = '<footer class="site_footer"><span>ver: v1.0.0</span><span>&copy; ' . date('Y') . ' Mars Haven Control System</span></footer>';
}

if ($custom_footer_html !== '') {
	echo $custom_footer_html;
}

if ($close_content_wrapper) {
	echo '</div>';
}
?>

</body>
</html>
