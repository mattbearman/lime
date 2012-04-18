<?require $this->core->template_path.'_includes/header.php'?>

<?=$this->content?>

<ul>
<?foreach($this->siblings() as $page):?>
<li><a href="<?=$page->link?>"><?=$page->title?></a></li>
<?endforeach?>
</ul>

<?require $this->core->template_path.'_includes/footer.php'?>

