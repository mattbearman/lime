<?require $this->core->template_path.'_includes/header.php'?>

<?=$this->content?>

<ul>
<?foreach($this->siblings() as $page):?>
<li><a href="<?=$page->link?>"><?=$page->title?></a> <em><?=date('j M Y', $page->date)?></em></li>
<?endforeach?>
</ul>

<?require $this->core->template_path.'_includes/footer.php'?>

