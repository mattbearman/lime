<?$this->title = "Matt Bearman"?><?require $this->core->template_path.'_includes/header.php'?>

			<div class="hero-unit">
				<?=$this->content?>
			</div>
			
			<?foreach($this->siblings() as $page):?>
			<h2>
				<?=$page->title?> 
				<?if($page->date):?>
				<small><?=date('j M Y', strtotime($page->date))?></small>
				<?endif?>
			</h2>
				
			<?=$page->excerpt(50)?>
			<p><a href="<?=$page->link?>">Read More</a></p>
			<hr>
			<?endforeach?>
			
<?require $this->core->template_path.'_includes/footer.php'?>
